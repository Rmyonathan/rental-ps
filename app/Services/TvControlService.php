<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TvControlService
{
    protected $baseUrl;

    public function __construct()
    {
        // The base URL of the Python ADB API server from your .env file or config
        $this->baseUrl = config('services.adb_api.url', 'http://localhost:3001');
    }

    /**
     * The single, centralized method for sending requests to the Python ADB API.
     *
     * @param string $method HTTP method ('get', 'post')
     * @param string $endpoint The API endpoint (e.g., '/health')
     * @param array $data The data payload for the request
     * @return array The JSON response as an associative array
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $response = Http::timeout(20) // Set a generous 20-second timeout
                ->{$method}($this->baseUrl . $endpoint, $data);

            if ($response->failed()) {
                $errorDetails = $response->json('error', 'An unknown HTTP error occurred.');
                Log::error("ADB API Error: Failed to call {$endpoint}", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'data' => $data
                ]);
                return ['success' => false, 'error' => $errorDetails];
            }

            // Return the JSON response, or an empty array if the body is empty
            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("ADB API ConnectionException: Could not connect to the Python server at {$this->baseUrl}", [
                'exception' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Connection refused. Is the Python ADB server running?'];
        } catch (\Exception $e) {
            Log::error("ADB API Generic Exception: An error occurred.", [
                'exception' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }

    // --- Core Rental Flow Methods ---

    public function switchToHdmi(string $tvIp): array
    {
        return $this->sendRequest('post', '/switch-to-hdmi2', ['tv_ip' => $tvIp]);
    }

    public function playTimeoutVideo(string $tvIp, int $rentalId): array
    {
        return $this->sendRequest('post', '/play-timeout-video', [
            'tv_ip' => $tvIp,
            'rental_id' => $rentalId
        ]);
    }
    
    // --- TV & Server Management Methods ---

    public function testConnection(string $tvIp): array
    {
        return $this->sendRequest('post', '/test-connection', ['tv_ip' => $tvIp]);
    }
    
    public function sendControl(string $tvIp, string $action): array
    {
        return $this->sendRequest('post', '/tv-control', [
            'tv_ip' => $tvIp,
            'action' => $action
        ]);
    }

    public function getApiHealth(): array
    {
        return $this->sendRequest('get', '/health');
    }
    
    public function getConfiguredTvs(): array
    {
        $health = $this->getApiHealth();
        
        // --- FIX: Check for 'status' key instead of 'success' ---
        if (isset($health['status']) && isset($health['configured_tvs'])) {
            $tvIps = [];
            // Filter to ensure only valid IPs are returned
            foreach ($health['configured_tvs'] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $tvIps[] = $ip;
                }
            }
            return $tvIps;
        }
        
        // Return an empty array if the health check fails or the structure is wrong
        return [];
    }

    /**
     * Efficiently tests all TVs at once.
     */
    public function testAllConnections(): array
    {
        return $this->sendRequest('post', '/test-all-connections');
    }

    public function getConnectedDevices(): array
    {
        return $this->sendRequest('get', '/devices');
    }

    public function restartAdbDaemon(): array
    {
        Log::info("Requesting ADB daemon restart via API.");
        return $this->sendRequest('post', '/restart-adb');
    }
    
    public function sendKeyEvent(string $tvIp, int $keycode): array
    {
        return $this->sendRequest('post', '/send-key', [
            'tv_ip' => $tvIp,
            'keycode' => $keycode
        ]);
    }

    // --- Monitoring Methods ---

    public function startRentalMonitor(string $tvIp, int $rentalId, int $durationInSeconds): array
    {
        return $this->sendRequest('post', '/start-rental-monitor', [
            'tv_ip' => $tvIp,
            'rental_id' => $rentalId,
            'timeout_seconds' => $durationInSeconds
        ]);
    }

    public function stopRentalMonitor(int $rentalId): array
    {
        return $this->sendRequest('post', "/stop-rental-monitor/{$rentalId}");
    }
    
    // --- Debugging Methods ---
    
    public function debugAdbPath(): array
    {
        return $this->sendRequest('get', '/test-adb');
    }
    public function switchHdmiInput(string $tvIp, string $hdmiInput): array
    {
        return $this->sendRequest('post', '/set-hdmi-input', [
            'tv_ip' => $tvIp,
            'target_input' => $hdmiInput,
        ]);
    }

    // // NEW: Add this function to get the current HDMI status
    public function getHdmiStatus(string $tvIp): array
    {
        return $this->sendRequest('post', '/get-hdmi-status', ['tv_ip' => $tvIp]);
    }
}
