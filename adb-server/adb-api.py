#!/usr/bin/env python3
"""
FastAPI ADB Control Server
A lightweight Python alternative to the Node.js ADB server
"""

import asyncio
import subprocess
import time
from datetime import datetime
from typing import Optional, Dict, Any

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pydantic import BaseModel
import uvicorn
import json

# Configuration
ADB_PATH = r"C:\Users\yonat\platform-tools\adb.exe"
VIDEO_PATH = "/sdcard/Movies/hot.mp4"
PORT = 3001

# Initialize FastAPI app
app = FastAPI(
    title="ADB Control Server",
    description="FastAPI server for controlling Android TV via ADB",
    version="1.0.0"
)

# Fixed CORS configuration for SSE and cross-origin requests
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:8000",
        "http://127.0.0.1:8000",
        "http://localhost:3000",
        "http://127.0.0.1:3000",
        "*"  # Allow all origins for development
    ],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=[
        "Accept",
        "Accept-Language",
        "Content-Language",
        "Content-Type",
        "Authorization",
        "Cache-Control",
        "Connection",
        "X-Requested-With",
        "X-CSRF-TOKEN",
        "Access-Control-Allow-Origin",
        "Access-Control-Allow-Headers",
        "*"
    ],
    expose_headers=[
        "Cache-Control",
        "Connection",
        "Content-Type",
        "Access-Control-Allow-Origin"
    ]
)

# Request models
class ConnectTVRequest(BaseModel):
    tv_ip: str

class SwitchHDMIRequest(BaseModel):
    tv_ip: str

class PlayVideoRequest(BaseModel):
    rental_id: int
    tv_ip: str

# Global state for tracking active rentals and their timeouts
active_rental_monitors = {}
timeout_tasks = {}

class RentalTimeoutRequest(BaseModel):
    rental_id: int
    tv_ip: str
    timeout_seconds: int = 30

# Response models
class BaseResponse(BaseModel):
    success: bool
    error: Optional[str] = None

class ADBTestResponse(BaseResponse):
    version: Optional[Dict[str, Any]] = None
    devices: Optional[Dict[str, Any]] = None
    adb_path: str

class ConnectResponse(BaseResponse):
    message: Optional[str] = None
    connect_output: Optional[str] = None
    devices: Optional[str] = None

class DevicesResponse(BaseResponse):
    devices: Optional[str] = None

class HealthResponse(BaseModel):
    status: str
    timestamp: str
    adb_path: str

# Utility functions
async def execute_adb(command: str, timeout: int = 10) -> Dict[str, Any]:
    """Execute ADB command asynchronously"""
    full_command = f'"{ADB_PATH}" {command}'
    print(f"Executing: {full_command}")
    
    try:
        process = await asyncio.create_subprocess_shell(
            full_command,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        
        try:
            stdout, stderr = await asyncio.wait_for(
                process.communicate(), 
                timeout=timeout
            )
            
            stdout_str = stdout.decode('utf-8', errors='ignore').strip()
            stderr_str = stderr.decode('utf-8', errors='ignore').strip()
            
            if process.returncode == 0:
                print(f"Success: {stdout_str}")
                return {
                    "success": True,
                    "stdout": stdout_str,
                    "stderr": stderr_str
                }
            else:
                print(f"Error (code {process.returncode}): {stderr_str}")
                return {
                    "success": False,
                    "error": stderr_str or f"Process failed with code {process.returncode}",
                    "stdout": stdout_str,
                    "stderr": stderr_str
                }
                
        except asyncio.TimeoutError:
            process.kill()
            await process.wait()
            return {
                "success": False,
                "error": f"Command timeout after {timeout} seconds",
                "stdout": "",
                "stderr": ""
            }
            
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "stdout": "",
            "stderr": ""
        }

class SendKeyRequest(BaseModel):
    tv_ip: str
    keycode: int

# Utility functions for SSE
async def create_sse_response(generator):
    """Create Server-Sent Events response with proper headers"""
    return StreamingResponse(
        generator,
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "Access-Control-Allow-Origin": "*",
            "Access-Control-Allow-Headers": "Cache-Control, Connection, Content-Type",
            "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
            "Access-Control-Expose-Headers": "Cache-Control, Connection, Content-Type"
        }
    )

async def send_sse_message(event_type: str, data: dict):
    """Format SSE message"""
    return f"event: {event_type}\ndata: {json.dumps(data)}\n\n"

async def monitor_rental_timeout(rental_id: int, tv_ip: str, timeout_seconds: int):
    """Monitor a rental and auto-play timeout video after specified seconds"""
    try:
        print(f"⏰ Starting timeout monitor for rental {rental_id} (TV: {tv_ip}) - {timeout_seconds}s")
        
        # Wait for the timeout period
        await sleep_async(timeout_seconds)
        
        # Check if rental is still active (not cancelled)
        if rental_id in active_rental_monitors:
            print(f"🎬 Auto-playing timeout video for rental {rental_id}")
            
            # Play timeout video based on TV brand
            if "192.168.1.20" in tv_ip:  # Xiaomi TV
                result = await execute_adb(f"-s {tv_ip} shell input keyevent 3")  # Go home first
                await sleep_async(2)
                
                # Try to play the video
                commands = [
                    f'-s {tv_ip} shell am start -n org.videolan.vlc/org.videolan.vlc.gui.video.VideoPlayerActivity -d "file://{VIDEO_PATH}" --activity-clear-top',
                    f'-s {tv_ip} shell am start -a android.intent.action.VIEW -d "file://{VIDEO_PATH}" -t "video/mp4" --activity-clear-top',
                    f'-s {tv_ip} shell am start -n com.android.gallery3d/com.android.gallery3d.app.MovieActivity -d "file://{VIDEO_PATH}"'
                ]
            else:  # TCL TV (192.168.1.21)
                result = await execute_adb(f"-s {tv_ip} shell input keyevent 3")  # Go home first
                await sleep_async(2)
                
                # Command for TCL TV
                commands = [
                    f'-s {tv_ip} shell am start -a android.intent.action.VIEW -d "file:///sdcard/Movies/hot.mp4" -t "video/*" org.videolan.vlc'
                ]
            
            success = False
            for command in commands:
                result = await execute_adb(command)
                if result["success"]:
                    success = True
                    break
            
            # Notify via SSE that timeout video was played
            if rental_id in active_rental_monitors:
                try:
                    await active_rental_monitors[rental_id].put({
                        "type": "timeout_video_played",
                        "rental_id": rental_id,
                        "tv_ip": tv_ip,
                        "success": success,
                        "timestamp": datetime.now().isoformat()
                    })
                except:
                    pass  # Queue might be closed
            
            print(f"✅ Timeout video {'played successfully' if success else 'failed to play'} for rental {rental_id}")
    
    except asyncio.CancelledError:
        print(f"❌ Timeout monitor for rental {rental_id} was cancelled")
    except Exception as e:
        print(f"❌ Error in timeout monitor for rental {rental_id}: {str(e)}")

async def sleep_async(seconds: float):
    """Async sleep function"""
    await asyncio.sleep(seconds)

# Add OPTIONS handler for preflight requests
@app.options("/{path:path}")
async def options_handler(path: str):
    """Handle preflight OPTIONS requests"""
    return {
        "message": "OK"
    }

# SSE Routes
@app.get("/events/{rental_id}")
async def rental_events(rental_id: int):
    """Server-Sent Events endpoint for rental monitoring"""
    
    async def event_generator():
        # Create a queue for this rental's events
        queue = asyncio.Queue()
        active_rental_monitors[rental_id] = queue
        
        try:
            # Send initial connection message
            yield await send_sse_message("connected", {
                "rental_id": rental_id,
                "message": f"Connected to rental {rental_id} monitoring",
                "timestamp": datetime.now().isoformat()
            })
            
            # Send periodic heartbeat and wait for events
            while True:
                try:
                    # Wait for events with timeout for heartbeat
                    event_data = await asyncio.wait_for(queue.get(), timeout=10.0)
                    yield await send_sse_message(event_data["type"], event_data)
                except asyncio.TimeoutError:
                    # Send heartbeat every 10 seconds
                    yield await send_sse_message("heartbeat", {
                        "rental_id": rental_id,
                        "timestamp": datetime.now().isoformat()
                    })
                
        except asyncio.CancelledError:
            pass
        except Exception as e:
            print(f"SSE error for rental {rental_id}: {str(e)}")
        finally:
            # Clean up when client disconnects
            if rental_id in active_rental_monitors:
                del active_rental_monitors[rental_id]
            if rental_id in timeout_tasks:
                timeout_tasks[rental_id].cancel()
                del timeout_tasks[rental_id]
    
    return await create_sse_response(event_generator())

@app.post("/start-rental-monitor")
async def start_rental_monitor(request: RentalTimeoutRequest):
    """Start monitoring a rental for timeout"""
    try:
        rental_id = request.rental_id
        
        # Cancel existing monitor if any
        if rental_id in timeout_tasks:
            timeout_tasks[rental_id].cancel()
        
        # Start new timeout monitor
        task = asyncio.create_task(
            monitor_rental_timeout(rental_id, request.tv_ip, request.timeout_seconds)
        )
        timeout_tasks[rental_id] = task
        
        # Send immediate notification if SSE connection exists
        if rental_id in active_rental_monitors:
            try:
                await active_rental_monitors[rental_id].put({
                    "type": "monitor_started",
                    "rental_id": rental_id,
                    "tv_ip": request.tv_ip,
                    "timeout_seconds": request.timeout_seconds,
                    "timestamp": datetime.now().isoformat()
                })
            except:
                pass
        
        return BaseResponse(
            success=True,
            error=None
        )
        
    except Exception as e:
        return BaseResponse(
            success=False,
            error=str(e)
        )

@app.post("/stop-rental-monitor/{rental_id}")
async def stop_rental_monitor(rental_id: int):
    """Stop monitoring a rental (when rental is completed/cancelled)"""
    try:
        # Cancel timeout task
        if rental_id in timeout_tasks:
            timeout_tasks[rental_id].cancel()
            del timeout_tasks[rental_id]
        
        # Notify via SSE
        if rental_id in active_rental_monitors:
            try:
                await active_rental_monitors[rental_id].put({
                    "type": "monitor_stopped",
                    "rental_id": rental_id,
                    "timestamp": datetime.now().isoformat()
                })
            except:
                pass
        
        return BaseResponse(
            success=True,
            error=None
        )
        
    except Exception as e:
        return BaseResponse(
            success=False,
            error=str(e)
        )

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint"""
    return HealthResponse(
        status="ADB Server is running",
        timestamp=datetime.now().isoformat(),
        adb_path=ADB_PATH
    )

@app.get("/test-adb", response_model=ADBTestResponse)
async def test_adb():
    """Test ADB connection and installation"""
    try:
        version_result = await execute_adb('version')
        devices_result = await execute_adb('devices')
        
        return ADBTestResponse(
            success=True,
            version=version_result,
            devices=devices_result,
            adb_path=ADB_PATH
        )
    except Exception as e:
        return ADBTestResponse(
            success=False,
            error=str(e),
            adb_path=ADB_PATH
        )

@app.post("/connect-tv", response_model=ConnectResponse)
async def connect_tv(request: ConnectTVRequest):
    """Connect to Android TV"""
    try:
        # First check if already connected
        devices_result = await execute_adb('devices')
        
        if devices_result["success"] and request.tv_ip in devices_result["stdout"]:
            return ConnectResponse(
                success=True,
                message=f"TV {request.tv_ip} is already connected",
                devices=devices_result["stdout"]
            )
        
        # Try to connect
        connect_result = await execute_adb(f"connect {request.tv_ip}:5555", 15)
        
        # Wait and verify connection
        await sleep_async(2)
        verify_result = await execute_adb('devices')
        
        if verify_result["success"] and request.tv_ip in verify_result["stdout"]:
            return ConnectResponse(
                success=True,
                message=f"Successfully connected to TV {request.tv_ip}",
                connect_output=connect_result["stdout"],
                devices=verify_result["stdout"]
            )
        else:
            return ConnectResponse(
                success=False,
                error="Connection failed - TV not found in devices list",
                connect_output=connect_result["stdout"],
                devices=verify_result["stdout"]
            )
            
    except Exception as e:
        return ConnectResponse(
            success=False,
            error=str(e)
        )

@app.post("/switch-to-hdmi2", response_model=BaseResponse)
async def switch_to_hdmi2(request: SwitchHDMIRequest):
    """Switch TV to HDMI 2 (Rental Start)"""
    try:
        print(f"Switching TV {request.tv_ip} to HDMI 2")
        
        if "192.168.1.20" in request.tv_ip:  # Xiaomi TV
            # Send TV Input key
            await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 178")
            await sleep_async(2)
            
            # Navigate down 3 times to HDMI 2
            for i in range(3):
                await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 20")  # DPAD_DOWN
                await sleep_async(1)
            
            # Select HDMI 2
            await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 23")  # DPAD_CENTER
            await sleep_async(3)
        else:  # TCL TV (192.168.1.21)
            # Send TV Input key
            await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 178")
            await sleep_async(2)
            
            # Navigate right 2 times to HDMI 1
            for i in range(2):
                await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 22")  # DPAD_RIGHT
                await sleep_async(1)
            
            # Select HDMI 2
            await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 23")  # DPAD_CENTER
            await sleep_async(3)
        
        return BaseResponse(
            success=True,
            error=None
        )
        
    except Exception as e:
        return BaseResponse(
            success=False,
            error=str(e)
        )

@app.post("/play-timeout-video", response_model=BaseResponse)
async def play_timeout_video(request: PlayVideoRequest):
    """Play timeout video on TV with consistent path handling"""
    try:
        print(f"Playing timeout video on TV {request.tv_ip}")
        
        # Go to home first
        await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent 3")
        await sleep_async(3)  # Increased delay
        
        # Define TV-specific video paths (you may need to adjust these)
        if "192.168.1.20" in request.tv_ip:  # Xiaomi TV
            video_path = VIDEO_PATH  # /sdcard/Movies/hot.mp4
            commands = [
                f'-s {request.tv_ip}:5555 shell am start -n org.videolan.vlc/org.videolan.vlc.gui.video.VideoPlayerActivity -d "file://{video_path}" --activity-clear-top',
                f'-s {request.tv_ip}:5555 shell am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/mp4" --activity-clear-top',
                f'-s {request.tv_ip}:5555 shell am start -n com.android.gallery3d/com.android.gallery3d.app.MovieActivity -d "file://{video_path}"'
            ]
        else:  # TCL TV (192.168.1.21)
            # First, let's verify the correct path for TCL TV
            video_path = "/sdcard/Movies/hot.mp4"  # Adjust this if needed
            commands = [
                f'-s {request.tv_ip}:5555 shell am start -n org.videolan.vlc/org.videolan.vlc.gui.video.VideoPlayerActivity -d "file://{video_path}" --activity-clear-top',
                f'-s {request.tv_ip}:5555 shell am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" --activity-clear-top'
            ]
        
        # Verify file exists first
        verify_result = await execute_adb(f"-s {request.tv_ip}:5555 shell ls -la '{video_path}'")
        if not verify_result["success"] or "hot.mp4" not in verify_result.get("stdout", ""):
            print(f"❌ Video file not found at {video_path}")
            return BaseResponse(
                success=False,
                error=f"Video file not found at {video_path} on TV {request.tv_ip}"
            )
        
        print(f"✅ Video file verified at {video_path}")
        
        success = False
        errors = []
        
        for i, command in enumerate(commands):
            print(f"Trying command {i+1}/{len(commands)}")
            result = await execute_adb(command, timeout=15)
            
            if result["success"]:
                print(f"✅ Command {i+1} succeeded")
                success = True
                break
            else:
                error_msg = result.get("error", "") or result.get("stderr", "Unknown error")
                errors.append(f"Cmd{i+1}: {error_msg}")
                print(f"❌ Command {i+1} failed: {error_msg}")
                
                # Wait between attempts
                if i < len(commands) - 1:
                    await sleep_async(2)
        
        if success:
            return BaseResponse(success=True, error=None)
        else:
            return BaseResponse(
                success=False,
                error=f"All commands failed for {video_path}: {'; '.join(errors)}"
            )
            
    except Exception as e:
        print(f"❌ Exception in play_timeout_video: {str(e)}")
        return BaseResponse(success=False, error=str(e))


@app.post("/send-key", response_model=BaseResponse)
async def send_key(request: SendKeyRequest):
    """Send key event to TV"""
    try:
        result = await execute_adb(f"-s {request.tv_ip}:5555 shell input keyevent {request.keycode}")
        
        return BaseResponse(
            success=result["success"],
            error=None if result["success"] else f"Failed to send key: {result.get('error', '')}"
        )
        
    except Exception as e:
        return BaseResponse(
            success=False,
            error=str(e)
        )

@app.post("/restart-adb", response_model=DevicesResponse)
async def restart_adb():
    """Restart ADB daemon"""
    try:
        print('Restarting ADB daemon...')
        
        # Kill server
        await execute_adb('kill-server')
        await sleep_async(2)
        
        # Start server
        await execute_adb('start-server')
        await sleep_async(3)
        
        # Test if working
        devices_result = await execute_adb('devices')
        
        return DevicesResponse(
            success=devices_result["success"],
            error=None if devices_result["success"] else "Failed to restart ADB daemon",
            devices=devices_result["stdout"]
        )
        
    except Exception as e:
        return DevicesResponse(
            success=False,
            error=str(e)
        )
    
@app.post("/rental-timeout", response_model=BaseResponse)
async def rental_timeout(request: dict):
    """Trigger the same timeout sequence that normal monitoring uses"""
    try:
        tv_ip = request.get('tv_ip')
        rental_id = request.get('rental_id')
        
        print(f"🎬 Manual rental timeout for rental {rental_id} on TV {tv_ip}")
        
        # Use the same logic as your normal timeout
        # This should match exactly what happens in your monitoring system
        result = await execute_timeout_sequence(tv_ip, rental_id)
        
        return BaseResponse(
            success=result.get('success', False),
            error=result.get('error')
        )
        
    except Exception as e:
        return BaseResponse(success=False, error=f"Manual timeout failed: {str(e)}")
    

    

async def execute_timeout_sequence(tv_ip: str, rental_id: int = None):
    """Execute the same timeout sequence used by normal monitoring"""
    try:
        # Go to home first
        await execute_adb(f"-s {tv_ip}:5555 shell input keyevent 3")
        await sleep_async(3)
        
        # Use the same video path logic as your working normal timeout
        if "192.168.1.20" in tv_ip:  # Xiaomi TV
            video_path = VIDEO_PATH
        else:  # TCL TV
            video_path = "/sdcard/Movies/hot.mp4"
        
        # Use the exact same commands that work in normal timeout
        command = f'-s {tv_ip}:5555 shell am start -n org.videolan.vlc/org.videolan.vlc.gui.video.VideoPlayerActivity -d "file://{video_path}" --activity-clear-top'
        
        result = await execute_adb(command, timeout=15)
        
        if result["success"]:
            print(f"✅ Manual timeout video started successfully")
            return {"success": True}
        else:
            print(f"❌ Manual timeout video failed: {result.get('error', '')}")
            return {"success": False, "error": result.get('error', 'Unknown error')}
            
    except Exception as e:
        return {"success": False, "error": str(e)}


@app.get("/devices", response_model=DevicesResponse)
async def get_devices():
    """Get connected devices"""
    try:
        result = await execute_adb('devices')
        
        return DevicesResponse(
            success=result["success"],
            devices=result["stdout"],
            error=result.get("error")
        )
    except Exception as e:
        return DevicesResponse(
            success=False,
            error=str(e)
        )
    
@app.post("/tv-control", response_model=BaseResponse)
async def control_tv(request: dict):
    """Control TV functions like volume and power"""
    try:
        tv_ip = request.get('tv_ip')
        action = request.get('action')
        
        if not tv_ip or not action:
            return BaseResponse(success=False, error="tv_ip and action are required")
        
        print(f"📺 Controlling TV {tv_ip}: {action}")
        
        # ADB commands for different actions
        commands = {
            'volume_up': ['adb', '-s', f'{tv_ip}:5555', 'shell', 'input', 'keyevent', 'KEYCODE_VOLUME_UP'],
            'volume_down': ['adb', '-s', f'{tv_ip}:5555', 'shell', 'input', 'keyevent', 'KEYCODE_VOLUME_DOWN'],
            'power_off': ['adb', '-s', f'{tv_ip}:5555', 'shell', 'input', 'keyevent', 'KEYCODE_POWER']
        }
        
        if action not in commands:
            return BaseResponse(success=False, error=f"Unknown action: {action}")
        
        # Execute the command
        result = subprocess.run(
            commands[action],
            capture_output=True,
            text=True,
            timeout=10
        )
        
        if result.returncode == 0:
            return BaseResponse(success=True, message=f"TV {action} executed successfully")
        else:
            return BaseResponse(success=False, error=f"Failed to execute {action}: {result.stderr}")
            
    except Exception as e:
        return BaseResponse(success=False, error=f"TV control failed: {str(e)}")
    
@app.post("/test-xiaomi", response_model=BaseResponse)
async def test_xiaomi_tv(request: dict):
    """Test Xiaomi TV (expects to stay connected in standby)"""
    tv_ip = "192.168.1.20"
    devices_result = await execute_adb('devices', timeout=5)
    
    if f"{tv_ip}:5555" in devices_result.get('stdout', '') and "offline" not in devices_result.get('stdout', ''):
        return BaseResponse(success=True, message="Xiaomi TV ready")
    else:
        connect_result = await execute_adb(f'connect {tv_ip}:5555', timeout=8)
        return BaseResponse(
            success="connected" in connect_result.get('stdout', ''),
            message="Xiaomi TV connected" if "connected" in connect_result.get('stdout', '') else "Connection failed"
        )
    
@app.post("/test-tcl", response_model=BaseResponse)
async def test_tcl_tv(request: dict):
    """Test TCL TV (handles offline status gracefully)"""
    tv_ip = "192.168.1.21"
    
    # For TCL, try direct command first since it might show offline but still work
    test_result = await execute_adb(f'-s {tv_ip}:5555 shell echo test', timeout=5)
    if test_result['success']:
        return BaseResponse(success=True, message="TCL TV responsive")
    
    # If that fails, try to connect
    connect_result = await execute_adb(f'connect {tv_ip}:5555', timeout=10)
    if "connected" in connect_result.get('stdout', ''):
        return BaseResponse(success=True, message="TCL TV connected")
    else:
        return BaseResponse(success=False, error="TCL TV unavailable")    


if __name__ == "__main__":
    print("🚀 ADB Control Server starting...")
    print(f"📱 ADB Path: {ADB_PATH}")
    print(f"🎬 Video Path: {VIDEO_PATH}")
    print(f"🌐 Server will run on http://localhost:{PORT}")
    print("\n📋 Available Endpoints:")
    print("  GET  /health - Health check")
    print("  GET  /test-adb - Test ADB installation")
    print("  GET  /devices - Get connected devices")
    print("  POST /connect-tv - Connect to TV")
    print("  POST /switch-to-hdmi2 - Switch TV to HDMI 2")
    print("  POST /play-timeout-video - Play timeout video")
    print("  POST /send-key - Send key event")
    print("  POST /restart-adb - Restart ADB daemon")
    print("  GET  /docs - Interactive API documentation")
    print("  GET  /events/{rental_id} - Server-Sent Events for rental monitoring")
    
    uvicorn.run(app, host="0.0.0.0", port=PORT)
