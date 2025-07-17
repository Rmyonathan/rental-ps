<?php
// File: app/Services/TvControlService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TvControlService
{
    private string $adbServerUrl = 'http://localhost:3001';

    /**
     * Execute rental start sequence - Switch TV to HDMI 2 for PlayStation
     */
    public function executeRentalStartSequence(string $tvIp): array
    {
        Log::info("Executing rental start sequence for TV: {$tvIp}");

        // First ensure connection
        $connectionResult = $this->connectToTv($tvIp);
        if (!$connectionResult['success']) {
            return ['success' => false, 'error' => 'Failed to connect to TV: ' . $connectionResult['error']];
        }

        // Switch to HDMI 2
        $switchResult = $this->switchToHdmi2($tvIp);
        if (!$switchResult['success']) {
            return ['success' => false, 'error' => 'Failed to switch to HDMI 2: ' . $switchResult['error']];
        }

        Log::info("Rental start sequence completed successfully for {$tvIp}");
        return ['success' => true, 'message' => 'TV switched to HDMI 2 for PlayStation'];
    }

    /**
     * Execute timeout sequence - Play timeout video when rental ends
     */
    public function executeTimeoutSequence(string $tvIp): array
    {
        Log::info("Executing timeout sequence for TV: {$tvIp}");

        // First ensure connection
        $connectionResult = $this->connectToTv($tvIp);
        if (!$connectionResult['success']) {
            return ['success' => false, 'error' => 'Failed to connect to TV: ' . $connectionResult['error']];
        }

        // Play timeout video
        $videoResult = $this->playTimeoutVideo($tvIp);
        if (!$videoResult['success']) {
            return ['success' => false, 'error' => 'Failed to play timeout video: ' . $videoResult['error']];
        }

        Log::info("Timeout sequence completed for {$tvIp}");
        return ['success' => true, 'message' => 'Timeout video is now playing'];
    }

    /**
     * Connect to TV via ADB Server
     */
    private function connectToTv(string $tvIp): array
    {
        try {
            Log::info("Connecting to TV: {$tvIp}");
            
            $response = Http::timeout(20)->post("{$this->adbServerUrl}/connect-tv", [
                'tv_ip' => $tvIp
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Connection result: " . json_encode($data));
                return $data;
            } else {
                Log::error("HTTP request failed: " . $response->status());
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("Exception connecting to TV: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Switch TV to HDMI 2 input
     */
    private function switchToHdmi2(string $tvIp): array
    {
        try {
            Log::info("Switching TV {$tvIp} to HDMI 2");

            $response = Http::timeout(15)->post("{$this->adbServerUrl}/switch-to-hdmi2", [
                'tv_ip' => $tvIp
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("HDMI switch result: " . json_encode($data));
                return $data;
            } else {
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("HDMI switch error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Play the timeout video
     */
    private function playTimeoutVideo(string $tvIp): array
    {
        try {
            Log::info("Playing timeout video on TV {$tvIp}");

            $response = Http::timeout(15)->post("{$this->adbServerUrl}/play-timeout-video", [
                'tv_ip' => $tvIp
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Video play result: " . json_encode($data));
                return $data;
            } else {
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("Video play error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a key event to the TV
     */
    public function sendKeyEvent(string $tvIp, int $keycode): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->adbServerUrl}/send-key", [
                'tv_ip' => $tvIp,
                'keycode' => $keycode
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test connection to TV
     */
    public function testConnection(string $tvIp): array
    {
        try {
            // Use TV-specific test endpoint
            $endpoint = $tvIp === '192.168.1.20' ? '/test-xiaomi' : '/test-tcl';
            
            $response = Http::timeout(30)->post("{$this->adbServerUrl}{$endpoint}", [
                'tv_ip' => $tvIp
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? false,
                    'message' => $data['message'] ?? $data['error'] ?? 'Unknown status'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to communicate with ADB server'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test error: ' . $e->getMessage()
            ];
        }
    }



    /**
     * Get current connected devices
     */
    public function getConnectedDevices(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->adbServerUrl}/devices");

            if ($response->successful()) {
                return $response->json();
            } else {
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Restart ADB daemon
     */
    public function restartAdbDaemon(): array
    {
        try {
            Log::info("Restarting ADB daemon via server");

            $response = Http::timeout(20)->post("{$this->adbServerUrl}/restart-adb");

            if ($response->successful()) {
                $data = $response->json();
                Log::info("ADB restart result: " . json_encode($data));
                return $data;
            } else {
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("ADB restart error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function playTimeoutVideoOnly(string $tvIp): array
    {
        Log::info("Playing timeout video directly on TV: {$tvIp}");

        try {
            // First ensure connection
            $connectionResult = $this->connectToTv($tvIp);
            if (!$connectionResult['success']) {
                return ['success' => false, 'error' => 'Failed to connect to TV: ' . $connectionResult['error']];
            }

            // Play timeout video with extended timeout
            $response = Http::timeout(30)->post("{$this->adbServerUrl}/play-timeout-video", [
                'tv_ip' => $tvIp
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Direct timeout video result: " . json_encode($data));
                return $data;
            } else {
                return ['success' => false, 'error' => 'HTTP request failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("Direct timeout video error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test ADB server health
     */
    public function testAdbServer(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->adbServerUrl}/health");

            if ($response->successful()) {
                return $response->json();
            } else {
                return ['success' => false, 'error' => 'ADB Server not responding'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Cannot connect to ADB Server: ' . $e->getMessage()];
        }
    }

    public function stopRentalMonitor(int $rentalId): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->adbServerUrl}/stop-rental-monitor/{$rentalId}");
            return $response->successful() ? $response->json() : ['success' => false, 'error' => 'Failed to stop monitor'];
        } catch (\Exception $e) {
            Log::error("Failed to stop rental monitor: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function triggerRentalTimeout(string $tvIp, int $rentalId): array
    {
        try {
            // Use the same endpoint that normal timeout uses
            $response = Http::timeout(60)->post("{$this->adbServerUrl}/rental-timeout", [
                'tv_ip' => $tvIp,
                'rental_id' => $rentalId
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                return ['success' => false, 'error' => 'Timeout trigger failed: ' . $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("Trigger timeout error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function controlTv(string $tvIp, string $action): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->adbServerUrl}/tv-control", [
                'tv_ip' => $tvIp,
                'action' => $action
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? true,
                    'message' => $data['message'] ?? 'TV control executed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to communicate with ADB server'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'TV control error: ' . $e->getMessage()
            ];
        }
    }


    

    /**
     * Debug ADB server connection
     */
    public function debugAdbPath(): array
    {
        try {
            // Test server health
            $healthResponse = Http::timeout(5)->get("{$this->adbServerUrl}/health");
            $testResponse = Http::timeout(10)->get("{$this->adbServerUrl}/test-adb");

            return [
                'server_url' => $this->adbServerUrl,
                'server_health' => $healthResponse->successful() ? $healthResponse->json() : ['error' => 'Server not responding'],
                'adb_test' => $testResponse->successful() ? $testResponse->json() : ['error' => 'ADB test failed']
            ];
        } catch (\Exception $e) {
            return [
                'server_url' => $this->adbServerUrl,
                'error' => $e->getMessage(),
                'suggestion' => 'Make sure to start the Node.js ADB server: node adb-server.js'
            ];
        }
    }
}