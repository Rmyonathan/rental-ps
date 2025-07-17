// File: adb-server.js
const express = require('express');
const { exec, spawn } = require('child_process');
const path = require('path');
const app = express();
const PORT = 3001;

// Middleware
app.use(express.json());

// ADB Configuration
const ADB_PATH = 'C:\\Users\\yonat\\platform-tools\\adb.exe';
const VIDEO_PATH = '/sdcard/Movies/hot.mp4';

// Utility function to execute ADB commands
function executeAdb(command, timeout = 10000) {
    return new Promise((resolve, reject) => {
        const fullCommand = `"${ADB_PATH}" ${command}`;
        console.log(`Executing: ${fullCommand}`);
        
        const process = exec(fullCommand, { timeout }, (error, stdout, stderr) => {
            if (error) {
                console.error(`Error: ${error.message}`);
                resolve({ success: false, error: error.message, stdout, stderr });
            } else {
                console.log(`Success: ${stdout}`);
                resolve({ success: true, stdout, stderr });
            }
        });
    });
}

// Sleep function
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// API Routes

// 1. Test ADB Connection
app.get('/test-adb', async (req, res) => {
    try {
        const versionResult = await executeAdb('version');
        const devicesResult = await executeAdb('devices');
        
        res.json({
            success: true,
            version: versionResult,
            devices: devicesResult,
            adb_path: ADB_PATH
        });
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// 2. Connect to TV
app.post('/connect-tv', async (req, res) => {
    const { tv_ip } = req.body;
    
    if (!tv_ip) {
        return res.json({ success: false, error: 'TV IP is required' });
    }
    
    try {
        // First check if already connected
        const devicesResult = await executeAdb('devices');
        
        if (devicesResult.success && devicesResult.stdout.includes(tv_ip)) {
            return res.json({
                success: true,
                message: `TV ${tv_ip} is already connected`,
                devices: devicesResult.stdout
            });
        }
        
        // Try to connect
        const connectResult = await executeAdb(`connect ${tv_ip}:5555`, 15000);
        
        // Wait and verify connection
        await sleep(2000);
        const verifyResult = await executeAdb('devices');
        
        if (verifyResult.success && verifyResult.stdout.includes(tv_ip)) {
            res.json({
                success: true,
                message: `Successfully connected to TV ${tv_ip}`,
                connect_output: connectResult.stdout,
                devices: verifyResult.stdout
            });
        } else {
            res.json({
                success: false,
                error: 'Connection failed - TV not found in devices list',
                connect_output: connectResult.stdout,
                devices: verifyResult.stdout
            });
        }
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// 3. Switch TV to HDMI 2 (Rental Start)
app.post('/switch-to-hdmi2', async (req, res) => {
    const { tv_ip } = req.body;
    
    if (!tv_ip) {
        return res.json({ success: false, error: 'TV IP is required' });
    }
    
    try {
        console.log(`Switching TV ${tv_ip} to HDMI 2`);
        
        // Send TV Input key
        await executeAdb(`-s ${tv_ip}:5555 shell input keyevent 178`);
        await sleep(2000);
        
        // Navigate down 3 times to HDMI 2
        for (let i = 0; i < 3; i++) {
            await executeAdb(`-s ${tv_ip}:5555 shell input keyevent 20`); // DPAD_DOWN
            await sleep(1000);
        }
        
        // Select HDMI 2
        await executeAdb(`-s ${tv_ip}:5555 shell input keyevent 23`); // DPAD_CENTER
        await sleep(3000);
        
        res.json({
            success: true,
            message: `TV ${tv_ip} switched to HDMI 2 successfully`
        });
        
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// 4. Play Timeout Video
app.post('/play-timeout-video', async (req, res) => {
    const { tv_ip } = req.body;
    
    if (!tv_ip) {
        return res.json({ success: false, error: 'TV IP is required' });
    }
    
    try {
        console.log(`Playing timeout video on TV ${tv_ip}`);
        
        // Go to home first
        await executeAdb(`-s ${tv_ip}:5555 shell input keyevent 3`);
        await sleep(2000);
        
        // Try multiple methods to play video
        const commands = [
            `-s ${tv_ip}:5555 shell am start -n org.videolan.vlc/org.videolan.vlc.gui.video.VideoPlayerActivity -d "file://${VIDEO_PATH}" --activity-clear-top`,
            `-s ${tv_ip}:5555 shell am start -a android.intent.action.VIEW -d "file://${VIDEO_PATH}" -t "video/mp4" --activity-clear-top`,
            `-s ${tv_ip}:5555 shell am start -n com.android.gallery3d/com.android.gallery3d.app.MovieActivity -d "file://${VIDEO_PATH}"`
        ];
        
        let success = false;
        let lastError = '';
        
        for (const command of commands) {
            const result = await executeAdb(command);
            if (result.success) {
                success = true;
                break;
            } else {
                lastError = result.error || result.stderr;
            }
        }
        
        if (success) {
            res.json({
                success: true,
                message: `Timeout video started on TV ${tv_ip}`
            });
        } else {
            res.json({
                success: false,
                error: `Failed to start timeout video: ${lastError}`
            });
        }
        
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// 5. Send Key Event
app.post('/send-key', async (req, res) => {
    const { tv_ip, keycode } = req.body;
    
    if (!tv_ip || !keycode) {
        return res.json({ success: false, error: 'TV IP and keycode are required' });
    }
    
    try {
        const result = await executeAdb(`-s ${tv_ip}:5555 shell input keyevent ${keycode}`);
        
        res.json({
            success: result.success,
            message: result.success ? `Key ${keycode} sent to TV ${tv_ip}` : `Failed to send key: ${result.error}`
        });
        
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// 6. Restart ADB Daemon
app.post('/restart-adb', async (req, res) => {
    try {
        console.log('Restarting ADB daemon...');
        
        // Kill server
        await executeAdb('kill-server');
        await sleep(2000);
        
        // Start server
        await executeAdb('start-server');
        await sleep(3000);
        
        // Test if working
        const devicesResult = await executeAdb('devices');
        
        res.json({
            success: devicesResult.success,
            message: devicesResult.success ? 'ADB daemon restarted successfully' : 'Failed to restart ADB daemon',
            devices: devicesResult.stdout
        });
        
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// 7. Get Connected Devices
app.get('/devices', async (req, res) => {
    try {
        const result = await executeAdb('devices');
        
        res.json({
            success: result.success,
            devices: result.stdout,
            error: result.error
        });
    } catch (error) {
        res.json({
            success: false,
            error: error.message
        });
    }
});

// Health check
app.get('/health', (req, res) => {
    res.json({
        status: 'ADB Server is running',
        timestamp: new Date().toISOString(),
        adb_path: ADB_PATH
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`🚀 ADB Control Server running on http://localhost:${PORT}`);
    console.log(`📱 ADB Path: ${ADB_PATH}`);
    console.log(`🎬 Video Path: ${VIDEO_PATH}`);
    console.log('\n📋 Available Endpoints:');
    console.log('  GET  /health - Health check');
    console.log('  GET  /test-adb - Test ADB installation');
    console.log('  GET  /devices - Get connected devices');
    console.log('  POST /connect-tv - Connect to TV');
    console.log('  POST /switch-to-hdmi2 - Switch TV to HDMI 2');
    console.log('  POST /play-timeout-video - Play timeout video');
    console.log('  POST /send-key - Send key event');
    console.log('  POST /restart-adb - Restart ADB daemon');
});