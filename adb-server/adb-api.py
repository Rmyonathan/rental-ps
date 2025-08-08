#!/usr/bin/env python3
"""
FastAPI ADB Control Server
A lightweight, scalable, and configurable Python server for managing Android TVs.
Version: 2.4.0 - Enhanced HDMI Control
"""

import asyncio
import re
import subprocess
from datetime import datetime
from typing import Optional, Dict, Any, List

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse, JSONResponse
from pydantic import BaseModel
import uvicorn
import json

# ==============================================================================
# --- 1. CORE CONFIGURATION ---
# ==============================================================================

# Set the full path to your ADB executable file
ADB_PATH = r"C:\Users\LOQ 15\Downloads\platform-tools-latest-windows\platform-tools\adb.exe"

# Set the network port for this server to run on
PORT = 3001

# --- 2. TV DEVICE CONFIGURATION ---
# This is the central place to manage all your TVs.
# To add a new TV, add its IP address and define its command sequences.
TV_CONFIGS = {
    # Configuration for the Xiaomi TV
    "192.168.1.20": {
        "model": "xiaomi",
        "video_path": "/sdcard/Movies/hot.mp4",
        # Command sequence to switch to the correct HDMI input for rentals
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",             # Wait for the menu to appear
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER (Selects the input)
        ],
        # List of commands to try for playing the timeout video
        "play_video_commands": [
            'am start -n org.videolan.vlc/org.videolan.vlc.gui.video.VideoPlayerActivity -d "file://{video_path}" --activity-clear-top',
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/mp4" --activity-clear-top'
        ]
    },
    
    # NEW: Specific configuration for the exceptional TCL TV
    "192.168.1.35": {
        "model": "tcl-special",
        "video_path": "/sdcard/Movies/hot.mp4",
        # Custom command sequence: input, down x4, ok
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",             # Wait for the menu to appear
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER (Selects the input)
        ],
        "play_video_commands": [
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" org.videolan.vlc'
        ]
    },

    # Make 192.168.1.37 behave like 192.168.1.35 (down x4 input sequence)
    "192.168.1.37": {
        "model": "tcl-special",
        "video_path": "/sdcard/Movies/hot.mp4",
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",             # Wait for the menu to appear
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER
        ],
        "play_video_commands": [
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" org.videolan.vlc'
        ]
    },

    "192.168.1.38": {
        "model": "tcl-special",
        "video_path": "/sdcard/Movies/hot.mp4",
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",             # Wait for the menu to appear
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 20",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER
        ],
        "play_video_commands": [
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" org.videolan.vlc'
        ]
    },

    "192.168.1.39": {
        "model": "tcl-special",
        "video_path": "/sdcard/Movies/hot.mp4",
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",             # Wait for the menu to appear
            "input keyevent 22",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 22",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER
        ],
        "play_video_commands": [
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" org.videolan.vlc'
        ]
    },

    "192.168.1.33": {
        "model": "tcl-special",
        "video_path": "/sdcard/Movies/hot.mp4",
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",             # Wait for the menu to appear
            "input keyevent 22",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 22",   # DPAD_DOWN
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER
        ],
        "play_video_commands": [
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" org.videolan.vlc'
        ]
    },
    
    # This is a default template for all your TCL TVs
    "DEFAULT_TCL": {
        "model": "tcl",
        "video_path": "/sdcard/Movies/hot.mp4",
        # Command sequence for TCL TVs
        "hdmi_switch_commands": [
            "input keyevent 178",  # Open TV Input source menu
            "sleep 2",
            "input keyevent 22",   # DPAD_RIGHT
            "sleep 1",
            "input keyevent 22",   # DPAD_RIGHT
            "sleep 1",
            "input keyevent 22",   # DPAD_RIGHT
            "sleep 1",
            "input keyevent 23"    # DPAD_CENTER (Selects the input)
        ],
        # // NEW: Direct command sequences for each HDMI input.
        # This is exactly what you specified: Open Menu, Navigate, Select.
        "hdmi_switch_sequences": {
            "hdmi1": [
                "input keyevent 178",  # Open TV Input source menu
                "sleep 2",
                "input keyevent 21",   # DPAD_LEFT
                "sleep 1",
                "input keyevent 23"    # DPAD_CENTER (Select)
            ],
            "hdmi2": [
                "input keyevent 178",  # Open TV Input source menu
                "sleep 2",
                "input keyevent 22",   # DPAD_RIGHT
                "sleep 1",
                "input keyevent 23"    # DPAD_CENTER (Select)
            ]
        },
        # // NEW: This is still needed for the status check feature.
        "hdmi_status_map": {
            "com.tcl.tvinput/tcl.hdmi.HDMIInputService/HW15": "hdmi1",
            "com.tcl.tvinput/tcl.hdmi.HDMIInputService/HW16": "hdmi2"
        },
        # Command for playing video on TCL TVs
        "play_video_commands": [
            'am start -a android.intent.action.VIEW -d "file://{video_path}" -t "video/*" org.videolan.vlc'
        ]
    }
}
ALL_TV_IPS = [
    "192.168.1.20", "192.168.1.37", "192.168.1.40", "192.168.1.31", "192.168.1.39", "192.168.1.33",
    "192.168.1.34", "192.168.1.35", "192.168.1.36", "192.168.1.37", "192.168.1.38"
]
# --- 3. LIST OF TCL TV IPs ---
# Add all your TCL TV IP addresses here. They will automatically use the "DEFAULT_TCL" configuration.
TCL_TV_IPS = [
    "192.168.1.37", "192.168.1.40", "192.168.1.31", "192.168.1.39", "192.168.1.33", 
    "192.168.1.34", "192.168.1.35", "192.168.1.36", "192.168.1.37", "192.168.1.38"
]

# Automatically apply the default TCL config to all TCL IPs
for ip in TCL_TV_IPS:
    if ip not in TV_CONFIGS:
        TV_CONFIGS[ip] = TV_CONFIGS["DEFAULT_TCL"]

# ==============================================================================
# --- APPLICATION SETUP ---
# ==============================================================================

app = FastAPI(
    title="ADB Control Server",
    description="A scalable FastAPI server for controlling Android TVs via ADB.",
    version="2.4.0" # // Version updated
)

# Allow Cross-Origin Resource Sharing (CORS) for communication with the Laravel frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], # Allows all origins
    allow_credentials=True,
    allow_methods=["*"], # Allows all methods (GET, POST, etc.)
    allow_headers=["*"], # Allows all headers
)

# Global state for managing real-time rental monitoring
active_rental_monitors: Dict[int, asyncio.Queue] = {}
timeout_tasks: Dict[int, asyncio.Task] = {}

# ==============================================================================
# --- Pydantic Models (for API Request and Response validation) ---
# ==============================================================================

class TVRequest(BaseModel):
    tv_ip: str

class PlayVideoRequest(TVRequest):
    rental_id: int

class RentalTimeoutRequest(TVRequest):
    rental_id: int
    timeout_seconds: int = 30

class TVControlRequest(TVRequest):
    action: str  # e.g., 'volume_up', 'volume_down', 'power_off'
    
class SendKeyRequest(TVRequest):
    keycode: int

class BaseResponse(BaseModel):
    success: bool
    message: Optional[str] = None
    error: Optional[str] = None

class DevicesResponse(BaseResponse):
    devices: Optional[str] = None

# // NEW: A request model for the new functions
class SetHDMIRequest(TVRequest):
    target_input: str  # This will be 'hdmi1' or 'hdmi2'

# // NEW: A response model for the status checking function
class HDMIStatusResponse(BaseResponse):
    hdmi_status: Optional[str] = None

# ==============================================================================
# --- CORE ADB & HELPER FUNCTIONS ---
# ==============================================================================

def get_tv_config(tv_ip: str) -> Dict[str, Any]:
    """Retrieves the configuration for a given TV IP, falling back to the default if not found."""
    config = TV_CONFIGS.get(tv_ip)
    if not config:
        print(f"⚠️ Warning: No specific config for {tv_ip}. Using default TCL config.")
        return TV_CONFIGS["DEFAULT_TCL"]
    return config

async def execute_adb_command(command: str, timeout: int = 10) -> Dict[str, Any]:
    """Executes a full ADB command string asynchronously and returns the result."""
    full_command = f'"{ADB_PATH}" {command}'
    print(f"Executing: {full_command}")
    
    try:
        process = await asyncio.create_subprocess_shell(
            full_command,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(process.communicate(), timeout=timeout)
        
        stdout_str = stdout.decode('utf-8', errors='ignore').strip()
        stderr_str = stderr.decode('utf-8', errors='ignore').strip()
        
        if process.returncode == 0:
            return {"success": True, "output": stdout_str}
        else:
            error_message = stderr_str or f"Process failed with code {process.returncode}"
            return {"success": False, "error": error_message, "output": stdout_str}
            
    except asyncio.TimeoutError:
        return {"success": False, "error": f"Command timed out after {timeout} seconds"}
    except Exception as e:
        return {"success": False, "error": str(e)}

async def execute_command_sequence(tv_ip: str, commands: List[str]):
    """Executes a sequence of ADB shell commands (e.g., for HDMI switching)."""
    for command in commands:
        if command.startswith("sleep"):
            await asyncio.sleep(int(command.split(" ")[1]))
        else:
            result = await execute_adb_command(f'-s {tv_ip}:5555 shell {command}')
            if not result["success"]:
                return result  # Stop and return on the first error
    return {"success": True}

# // NEW: This is the internal logic for the /get-hdmi-status endpoint
async def get_hdmi_status_internal(tv_ip: str) -> Dict[str, Any]:
    config = get_tv_config(tv_ip)
    status_map = config.get("hdmi_status_map", {})

    command = f'-s {tv_ip}:5555 shell "dumpsys window windows | grep mCurrentFocus"'
    result = await execute_adb_command(command)

    if not result["success"]:
        return {"success": False, "error": result.get("error")}

    raw_output = result.get("output", "")
    match = re.search(r'\{[^{}]+\s([^\s/]+/[^}\s]+)\}', raw_output)

    if match:
        focused_component = match.group(1)
        status = status_map.get(focused_component, "Unknown")
        return {"success": True, "hdmi_status": status}
    else:
        return {"success": True, "hdmi_status": "home_or_other"}

# ==============================================================================
# --- RENTAL MONITORING & SSE (Server-Sent Events) ---
# ==============================================================================

async def send_sse_message(event_type: str, data: dict):
    """Formats data into a Server-Sent Event message string."""
    return f"event: {event_type}\ndata: {json.dumps(data)}\n\n"

async def monitor_rental_timeout(rental_id: int, tv_ip: str, timeout_seconds: int):
    """A background task that waits for the rental duration and then plays the timeout video."""
    try:
        print(f"⏰ Starting timeout monitor for rental {rental_id} ({tv_ip}) for {timeout_seconds}s")
        await asyncio.sleep(timeout_seconds)
        
        if rental_id in timeout_tasks:  # Check if the task wasn't cancelled
            print(f"🎬 Timeout reached for rental {rental_id}. Playing video.")
            result = await play_timeout_video_internal(tv_ip, rental_id)
            if rental_id in active_rental_monitors:
                await active_rental_monitors[rental_id].put({
                    "type": "timeout_triggered",
                    "success": result["success"],
                    "errors": result.get("errors")
                })

    except asyncio.CancelledError:
        print(f"✅ Timeout monitor for rental {rental_id} was successfully cancelled.")
    except Exception as e:
        print(f"❌ Error in timeout monitor for rental {rental_id}: {e}")

@app.get("/events/{rental_id}")
async def rental_events_stream(rental_id: int):
    """SSE endpoint to stream real-time events for a specific rental."""
    async def event_generator():
        queue = asyncio.Queue()
        active_rental_monitors[rental_id] = queue
        try:
            yield await send_sse_message("connected", {"rental_id": rental_id})
            while True:
                event_data = await queue.get()
                yield await send_sse_message(event_data.get('type', 'message'), event_data)
        except asyncio.CancelledError:
            print(f"Client for rental {rental_id} disconnected.")
        finally:
            # Cleanup when the client disconnects
            if rental_id in active_rental_monitors:
                del active_rental_monitors[rental_id]
            if rental_id in timeout_tasks:
                timeout_tasks[rental_id].cancel()
                del timeout_tasks[rental_id]

    return StreamingResponse(event_generator(), media_type="text/event-stream")

# ==============================================================================
# --- API ENDPOINTS ---
# ==============================================================================

@app.options("/{path:path}")
async def options_handler(path: str):
    """Handles preflight OPTIONS requests for CORS."""
    return JSONResponse(content={"message": "OK"})

@app.get("/health")
async def health_check():
    """Provides a basic health check of the server and its configuration."""
    return {
        "status": "ADB Server is running",
        "timestamp": datetime.now().isoformat(),
        "adb_path": ADB_PATH,
        "configured_tvs": list(TV_CONFIGS.keys())
    }

@app.get("/test-adb")
async def test_adb_installation():
    """Tests the ADB installation and lists currently connected devices."""
    version_result = await execute_adb_command('version')
    devices_result = await execute_adb_command('devices')
    return {
        "success": version_result["success"] and devices_result["success"],
        "adb_version": version_result.get("output"),
        "connected_devices": devices_result.get("output"),
        "error": version_result.get("error") or devices_result.get("error")
    }
    
@app.get("/devices", response_model=DevicesResponse)
async def get_devices():
    """Gets a list of currently connected ADB devices."""
    result = await execute_adb_command('devices')
    return DevicesResponse(
        success=result["success"],
        devices=result.get("output"),
        error=result.get("error")
    )

@app.post("/connect-tv", response_model=BaseResponse)
async def connect_to_tv(request: TVRequest):
    """Establishes an ADB connection to a TV."""
    result = await execute_adb_command(f"connect {request.tv_ip}:5555", timeout=15)
    if "connected" in result.get("output", "") or "already connected" in result.get("output", ""):
        return BaseResponse(success=True, message=f"Successfully connected to {request.tv_ip}")
    else:
        return BaseResponse(success=False, error=result.get("error", "Connection failed"))

@app.post("/restart-adb", response_model=DevicesResponse)
async def restart_adb_server():
    """Kills and restarts the ADB server daemon."""
    await execute_adb_command('kill-server')
    await asyncio.sleep(2)
    await execute_adb_command('start-server')
    await asyncio.sleep(2)
    result = await execute_adb_command('devices')
    return DevicesResponse(
        success=result["success"],
        devices=result.get("output"),
        error=result.get("error")
    )

@app.post("/switch-to-hdmi2", response_model=BaseResponse)
async def switch_tv_to_hdmi2(request: TVRequest):
    """Switches the TV to the configured HDMI input at the start of a rental."""
    config = get_tv_config(request.tv_ip)
    result = await execute_command_sequence(request.tv_ip, config["hdmi_switch_commands"])
    if not result["success"]:
        return BaseResponse(success=False, error=f"Failed to switch HDMI: {result.get('error')}")
    return BaseResponse(success=True, message=f"TV {request.tv_ip} switched to HDMI input.")

async def play_timeout_video_internal(tv_ip: str, rental_id: int):
    """
    Internal logic for playing the timeout video, used by monitor and manual trigger.
    This version is more robust to prevent common playback failures.
    """
    config = get_tv_config(tv_ip)
    video_path = config["video_path"]
    vlc_package = "org.videolan.vlc"

    # 1. (NEW) Force stop the media player to clear any bad state.
    print(f"🎬 Resetting state for {tv_ip}: Forcing stop on {vlc_package}")
    await execute_adb_command(f"-s {tv_ip}:5555 shell am force-stop {vlc_package}")
    await asyncio.sleep(1)

    # 2. Go to the Home screen to ensure a clean start (this was already here).
    print(f"🎬 Resetting state for {tv_ip}: Sending HOME keyevent")
    await execute_adb_command(f"-s {tv_ip}:5555 shell input keyevent 3")
    await asyncio.sleep(2)

    # 3. Attempt to play the video using the configured commands.
    success = False
    errors = []
    for command_template in config["play_video_commands"]:
        command = f'-s {tv_ip}:5555 shell {command_template.format(video_path=video_path)}'
        result = await execute_adb_command(command, timeout=15)
        if result["success"]:
            # 4. (NEW) Optional but recommended: Check if VLC is now the focused app.
            await asyncio.sleep(2) # Give time for the app to launch
            check_result = await execute_adb_command(f"-s {tv_ip}:5555 shell dumpsys activity | findstr mFocusedActivity")
            if vlc_package in check_result.get("output", ""):
                print(f"✅ Playback confirmed on {tv_ip}.")
                success = True
                break # Exit the loop on first success
            else:
                errors.append("Command sent, but playback could not be confirmed.")
        else:
            errors.append(result.get("error", "Unknown error"))
    
    return {"success": success, "errors": errors}

@app.post("/play-timeout-video", response_model=BaseResponse)
async def play_timeout_video_endpoint(request: PlayVideoRequest):
    """API endpoint to manually trigger the timeout video for a rental."""
    result = await play_timeout_video_internal(request.tv_ip, request.rental_id)
    if not result["success"]:
        return BaseResponse(success=False, error=f"Failed to play video: {'; '.join(result['errors'])}")
    return BaseResponse(success=True, message=f"Timeout video started on {request.tv_ip}.")

@app.post("/rental-timeout", response_model=BaseResponse)
async def manual_rental_timeout(request: PlayVideoRequest):
    """Manually triggers the timeout sequence for a rental."""
    print(f"🎬 Manual rental timeout for rental {request.rental_id} on TV {request.tv_ip}")
    result = await play_timeout_video_internal(request.tv_ip, request.rental_id)
    if not result["success"]:
        return BaseResponse(success=False, error=f"Manual timeout failed: {'; '.join(result['errors'])}")
    return BaseResponse(success=True, message="Manual timeout sequence triggered.")

@app.post("/tv-control", response_model=BaseResponse)
async def control_tv(request: TVControlRequest):
    """Controls basic TV functions like volume and power."""
    key_map = {
        'volume_up': 'KEYCODE_VOLUME_UP',
        'volume_down': 'KEYCODE_VOLUME_DOWN',
        'power_off': 'KEYCODE_POWER'
    }
    if request.action not in key_map:
        return BaseResponse(success=False, error="Invalid action specified.")

    keycode = key_map[request.action]
    result = await execute_adb_command(f"-s {request.tv_ip}:5555 shell input keyevent {keycode}")
    
    if not result["success"]:
        return BaseResponse(success=False, error=f"Failed to execute '{request.action}': {result.get('error')}")
    return BaseResponse(success=True, message=f"Action '{request.action}' sent to {request.tv_ip}.")

@app.post("/send-key", response_model=BaseResponse)
async def send_key_event(request: SendKeyRequest):
    """Sends a raw keycode event to a TV."""
    result = await execute_adb_command(f"-s {request.tv_ip}:5555 shell input keyevent {request.keycode}")
    if not result["success"]:
        return BaseResponse(success=False, error=f"Failed to send keycode {request.keycode}: {result.get('error')}")
    return BaseResponse(success=True, message=f"Keycode {request.keycode} sent to {request.tv_ip}.")

@app.post("/test-connection", response_model=BaseResponse)
async def test_tv_connection(request: TVRequest):
    """Checks if a TV is online and responsive via ADB."""
    # A simple 'echo' command is a lightweight way to check for a response.
    result = await execute_adb_command(f"-s {request.tv_ip}:5555 shell echo online", timeout=5)
    if result.get("success") and "online" in result.get("output", ""):
        return BaseResponse(success=True, message="TV is online and responsive.")
    else:
        # If the lightweight check fails, try a full reconnect.
        connect_result = await connect_to_tv(request)
        if connect_result.success:
            return BaseResponse(success=True, message="TV was offline but reconnected successfully.")
        else:
            return BaseResponse(success=False, error=f"TV is offline. Connection attempt failed: {connect_result.error}")

@app.post("/start-rental-monitor", response_model=BaseResponse)
async def start_rental_monitor(request: RentalTimeoutRequest):
    """Starts the background timeout monitor for a new rental."""
    rental_id = request.rental_id
    if rental_id in timeout_tasks:
        timeout_tasks[rental_id].cancel()
    
    task = asyncio.create_task(
        monitor_rental_timeout(rental_id, request.tv_ip, request.timeout_seconds)
    )
    timeout_tasks[rental_id] = task
    return BaseResponse(success=True, message=f"Monitor started for rental {rental_id}.")

@app.post("/stop-rental-monitor/{rental_id}", response_model=BaseResponse)
async def stop_rental_monitor(rental_id: int):
    """Stops the background timeout monitor for a completed or cancelled rental."""
    if rental_id in timeout_tasks:
        timeout_tasks[rental_id].cancel()
        del timeout_tasks[rental_id]
        return BaseResponse(success=True, message=f"Monitor stopped for rental {rental_id}.")
    return BaseResponse(success=False, error=f"No active monitor found for rental {rental_id}.")

@app.post("/test-all-connections")
async def test_all_tv_connections():
    """NEW: Checks all configured TVs concurrently and returns their statuses."""
    async def check_one_tv(ip: str):
        result = await execute_adb_command(f"-s {ip}:5555 shell echo online", timeout=5)
        if result.get("success") and "online" in result.get("output", ""):
            return ip, {"success": True, "message": "Online"}
        else:
            connect_result = await execute_adb_command(f"connect {ip}:5555", timeout=10)
            if "connected" in connect_result.get("output", "") or "already connected" in connect_result.get("output", ""):
                 return ip, {"success": True, "message": "Reconnected"}
            else:
                 return ip, {"success": False, "error": "Offline"}
    tasks = [check_one_tv(ip) for ip in ALL_TV_IPS]
    results = await asyncio.gather(*tasks)
    return dict(results)

# // NEW: This is the /get-hdmi-status endpoint your frontend will call.
@app.post("/get-hdmi-status", response_model=HDMIStatusResponse)
async def get_hdmi_status_endpoint(request: TVRequest):
    """Gets the current active HDMI input for a specific TV."""
    status_result = await get_hdmi_status_internal(request.tv_ip)
    if not status_result["success"]:
        return HDMIStatusResponse(success=False, error=status_result.get("error"))

    return HDMIStatusResponse(
        success=True,
        message="Successfully retrieved HDMI status.",
        hdmi_status=status_result.get("hdmi_status"),
    )

# // NEW: This is the /set-hdmi-input endpoint that your Laravel app will call.
@app.post("/set-hdmi-input", response_model=BaseResponse)
async def set_hdmi_input(request: SetHDMIRequest):
    """Switches the TV to a target HDMI input by executing a predefined command sequence."""
    config = get_tv_config(request.tv_ip)
    target_input = request.target_input

    # 1. Get the correct command sequence from the config
    command_sequence = config.get("hdmi_switch_sequences", {}).get(target_input)

    if not command_sequence:
        return BaseResponse(success=False, error=f"No HDMI switch sequence found for '{target_input}'.")

    # 2. Execute the sequence
    result = await execute_command_sequence(request.tv_ip, command_sequence)

    if not result["success"]:
        return BaseResponse(success=False, error=f"Failed to switch to {target_input}: {result.get('error')}")

    return BaseResponse(success=True, message=f"TV {request.tv_ip} switched to {target_input}.")


if __name__ == "__main__":
    print("=============================================")
    print("🚀 ADB CONTROL SERVER v2.4 🚀")
    print("=============================================")
    print(f"📱 ADB Path: {ADB_PATH}")
    print(f"🌐 Server will run on http://localhost:{PORT}")
    print("\n📋 Use http://localhost:3001/docs for the interactive API documentation.")
    print("=============================================")
    uvicorn.run(app, host="0.0.0.0", port=PORT)
