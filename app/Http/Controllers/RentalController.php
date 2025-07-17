<?php
// File: app/Http/Controllers/RentalController.php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\TvControlService;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class RentalController extends Controller
{
    private $tvControlService;
    private string $adbPath = 'C:\Users\yonat\platform-tools\adb.exe'; // Full path to ADB

    public function __construct(TvControlService $tvControlService)
    {
        $this->tvControlService = $tvControlService;
    }

    public function index()
    {
        $activeRentals = Rental::with(['cafeOrders.items.cafeItem']) // Load cafe orders with items
            ->where('status', 'active')
            ->where('end_time', '>', Carbon::now())
            ->orderBy('end_time', 'asc')
            ->get();

        return view('rentals.index', compact('activeRentals'));
    }

    public function create(Request $request)
    {
        // Pass TV IP and station info if provided via URL parameters
        $tvIp = $request->get('tv_ip');
        $station = $request->get('station');
        
        return view('rentals.create', compact('tvIp', 'station'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'ps_station' => 'required|string|max:10',
            'tv_ip' => 'required|ip',
            'duration_minutes' => 'required|integer|min:1|max:480',
            'price' => 'required|numeric|min:0'
        ]);

        // Test TV connection first
        if (!$this->tvControlService->testConnection($request->tv_ip)) {
            return back()->withErrors(['tv_ip' => 'Cannot connect to TV at this IP address. Please check the connection and ensure ADB debugging is enabled.']);
        }

        $startTime = Carbon::now();
        $durationMinutes = (int) $request->duration_minutes;
        $endTime = $startTime->copy()->addMinutes($durationMinutes);

        // Create the rental record
        $rental = Rental::create([
            'customer_name' => $request->customer_name,
            'phone' => $request->phone,
            'ps_station' => $request->ps_station,
            'tv_ip' => $request->tv_ip,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'price' => (float) $request->price
        ]);

         // ✅ Record transaction
        \App\Services\TransactionService::recordRentalTransaction($rental);
        
        // Execute rental start sequence - Switch TV to HDMI 2 for PlayStation
        $startResult = $this->tvControlService->executeRentalStartSequence($request->tv_ip);
        
        if ($startResult['success']) {
            return redirect()->route('rentals.index')
                ->with('success', "Rental started for {$rental->customer_name} on {$rental->ps_station}! TV switched to HDMI 2 for PlayStation.");
        } else {
            return redirect()->route('rentals.index')
                ->with('warning', "Rental started for {$rental->customer_name} but failed to switch TV input: {$startResult['error']}. Please manually switch to HDMI 2.");
        }
    }

    // Updated extend method to handle both PATCH and POST requests
    public function extend(Request $request, $rental = null)
    {
        // Handle both route parameter and ID in URL
        if (is_null($rental)) {
            $rental = Rental::findOrFail($request->route('id'));
        } elseif (is_numeric($rental)) {
            $rental = Rental::findOrFail($rental);
        }

        $request->validate([
            'additional_minutes' => 'required|integer|min:1|max:240',
            'additional_price' => 'required|numeric|min:0'
        ]);

        if ($rental->status !== 'active') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Cannot extend inactive rental']);
            }
            return back()->withErrors(['error' => 'Cannot extend inactive rental']);
        }

        $additionalMinutes = (int) $request->additional_minutes;
        $additionalPrice = (float) $request->additional_price;

        $rental->update([
            'end_time' => $rental->end_time->addMinutes($additionalMinutes),
            'duration_minutes' => $rental->duration_minutes + $additionalMinutes,
            'price' => $rental->price + $additionalPrice
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true, 
                'message' => "Rental extended by {$additionalMinutes} minutes! New end time: {$rental->end_time->format('H:i')}"
            ]);
        }

        return back()->with('success', "Rental extended by {$additionalMinutes} minutes! New end time: {$rental->end_time->format('H:i')}");
    }

    // Updated complete method to handle both PATCH and POST requests
    public function complete($rental = null)
    {
        // Handle both route parameter and ID in URL
        if (is_null($rental)) {
            $rental = Rental::findOrFail(request()->route('id'));
        } elseif (is_numeric($rental)) {
            $rental = Rental::findOrFail($rental);
        }

        $rental->update(['status' => 'completed']);
        
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true, 
                'message' => "Rental for {$rental->customer_name} completed successfully!"
            ]);
        }

        return back()->with('success', "Rental for {$rental->customer_name} completed successfully!");
    }

    // Updated forceTimeout method to handle both route types
    public function forceTimeout($id)
    {
        try {
            $rental = Rental::findOrFail($id);
            
            // Update rental status first
            $rental->update([
                'end_time' => now(),
                'status' => 'completed'
            ]);

            // Use the TvControlService instead of direct HTTP call
            $result = $this->tvControlService->triggerRentalTimeout($rental->tv_ip, $rental->id);
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Rental ended successfully and timeout video is playing!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Rental ended but video playback failed: ' . ($result['error'] ?? 'Unknown error')
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Timeout failed: ' . $e->getMessage()
            ]);
        }
    }

    public function controlTv(Request $request)
    {
        try {
            $tvIp = $request->input('tv_ip');
            $action = $request->input('action');
            
            // Validate inputs
            if (!$tvIp || !$action) {
                return response()->json([
                    'success' => false,
                    'message' => 'TV IP and action are required'
                ]);
            }
            
            // Use TvControlService
            $tvControlService = new \App\Services\TvControlService();
            $result = $tvControlService->controlTv($tvIp, $action);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'TV control failed: ' . $e->getMessage()
            ]);
        }
    }


    public function updateEndTime(Request $request)
    {
        try {
            $rental = Rental::find($request->rental_id);
            if ($rental) {
                $rental->end_time = $request->new_end_time;
                $rental->save();
                return response()->json(['success' => true]);
            }
            return response()->json(['success' => false, 'message' => 'Rental not found']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update']);
        }
    }





    


    // Auto-check function for expired rentals - this runs automatically
    public function autoCheck()
    {
        $expiredRentals = Rental::where('status', 'active')
            ->where('end_time', '<=', Carbon::now())
            ->get();

        $results = [];
        
        foreach ($expiredRentals as $rental) {
            // When rental expires, play timeout video
            $result = $this->tvControlService->executeTimeoutSequence($rental->tv_ip);
            
            if ($result['success']) {
                $rental->update(['status' => 'completed']);
                $results[] = "✅ Timeout video playing for {$rental->customer_name} ({$rental->ps_station})";
            } else {
                $results[] = "❌ Failed timeout for {$rental->customer_name}: {$result['error']}";
            }
        }
        
        return response()->json([
            'checked_at' => now(),
            'expired_count' => $expiredRentals->count(),
            'results' => $results
        ]);
    }

    /**
     * Test TV connection via AJAX - Enhanced for card interface
     */
    public function testConnection(Request $request)
    {
        try {
            $tvIp = $request->input('tv_ip');
            
            if (!$tvIp) {
                return response()->json([
                    'success' => false,
                    'message' => 'TV IP is required'
                ]);
            }
            
            // Use the injected service (not new instance)
            $result = $this->tvControlService->testConnection($tvIp);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test specific TV connection for card interface
     */
    public function testTvConnection($tvIp)
    {
        $success = $this->tvControlService->testConnection($tvIp);
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Connected' : 'Offline',
            'tv_ip' => $tvIp,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Switch TV to PlayStation manually (HDMI 2)
     */
    public function switchToPlayStation(Rental $rental)
    {
        $result = $this->tvControlService->executeRentalStartSequence($rental->tv_ip);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['success'] 
                ? "TV switched to HDMI 2 for PlayStation on {$rental->ps_station}!" 
                : "Failed to switch TV input: {$result['error']}"
        ]);
    }

    /**
     * Show cafe menu for specific station
     */
    public function showCafeMenu($station)
    {
        // Define menu items (you can move this to a database table later)
        $menuItems = [
            'food' => [
                ['name' => 'Pizza Slice', 'price' => 5.00],
                ['name' => 'Burger', 'price' => 8.00],
                ['name' => 'Sandwich', 'price' => 6.00],
                ['name' => 'Fries', 'price' => 3.00],
            ],
            'drinks' => [
                ['name' => 'Soda', 'price' => 2.00],
                ['name' => 'Coffee', 'price' => 3.00],
                ['name' => 'Energy Drink', 'price' => 4.00],
                ['name' => 'Water', 'price' => 1.00],
            ]
        ];

        return response()->json([
            'success' => true,
            'station' => $station,
            'menu' => $menuItems
        ]);
    }
    
    public function show(Rental $rental)
    {
        $rental->load(['cafeOrders.items.cafeItem']);
        return view('rentals.show', compact('rental'));
    }

    /**
     * Create food order for station
     */
    public function createFoodOrder(Request $request, $station)
    {
        // This is a placeholder - implement food ordering logic here
        $request->validate([
            'items' => 'required|array',
            'total_price' => 'required|numeric|min:0'
        ]);

        // For now, just return success
        // Later you can create a FoodOrder model and save to database
        
        return response()->json([
            'success' => true,
            'message' => "Food order created for {$station}",
            'order_id' => 'FO-' . time(),
            'station' => $station
        ]);
    }

    /**
     * Get stations status for dashboard
     */
    public function stations()
    {
        $stations = [
            [
                'id' => 1,
                'name' => 'PlayStation Station 1',
                'tv_ip' => '192.168.1.20',
                'tv_brand' => 'Xiaomi',
                'status' => 'available'
            ],
            [
                'id' => 2,
                'name' => 'PlayStation Station 2',
                'tv_ip' => '192.168.1.21',
                'tv_brand' => 'TCL',
                'status' => 'available'
            ]
        ];

        // Check if stations have active rentals
        $activeRentals = Rental::where('status', 'active')
            ->where('end_time', '>', Carbon::now())
            ->get();

        foreach ($stations as &$station) {
            $rental = $activeRentals->where('tv_ip', $station['tv_ip'])->first();
            if ($rental) {
                $station['status'] = 'occupied';
                $station['rental'] = $rental;
            }
        }

        return response()->json(['stations' => $stations]);
    }

    /**
     * Refresh all stations status
     */
    public function refreshStations()
    {
        $stations = ['192.168.1.20', '192.168.1.21'];
        $results = [];

        foreach ($stations as $tvIp) {
            $success = $this->tvControlService->testConnection($tvIp);
            $results[$tvIp] = [
                'connected' => $success,
                'timestamp' => now()->toISOString()
            ];
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'message' => 'All stations refreshed'
        ]);
    }

    /**
     * Debug ADB connection (for troubleshooting)
     */
    public function debugConnection(Request $request)
    {
        $request->validate([
            'tv_ip' => 'required|ip'
        ]);

        $tvIp = $request->tv_ip;
        $debug = [];

        try {
            // Check if ADB is available
            $adbVersionProcess = new Process([$this->adbPath, 'version']);
            $adbVersionProcess->setTimeout(5);
            $adbVersionProcess->run();
            $debug['adb_available'] = $adbVersionProcess->isSuccessful();
            $debug['adb_version'] = $adbVersionProcess->getOutput();
            $debug['adb_path'] = $this->adbPath;
            $debug['adb_exists'] = file_exists($this->adbPath);

            // Check current devices
            $devicesProcess = new Process([$this->adbPath, 'devices']);
            $devicesProcess->setTimeout(5);
            $devicesProcess->run();
            $debug['current_devices'] = $devicesProcess->getOutput();
            $debug['devices_command_success'] = $devicesProcess->isSuccessful();

            // Try to connect to TV IP
            $connectProcess = new Process([$this->adbPath, 'connect', "{$tvIp}:5555"]);
            $connectProcess->setTimeout(10);
            $connectProcess->run();
            $debug['connect_output'] = $connectProcess->getOutput();
            $debug['connect_error'] = $connectProcess->getErrorOutput();
            $debug['connect_success'] = $connectProcess->isSuccessful();

            // Wait a moment before checking devices again
            sleep(1);

            // Check devices after connect
            $devicesAfterProcess = new Process([$this->adbPath, 'devices']);
            $devicesAfterProcess->setTimeout(5);
            $devicesAfterProcess->run();
            $debug['devices_after_connect'] = $devicesAfterProcess->getOutput();
            $debug['ip_found_in_devices'] = str_contains($devicesAfterProcess->getOutput(), $tvIp);

            // Try a simple shell command if connected
            if ($debug['ip_found_in_devices']) {
                $shellTestProcess = new Process([$this->adbPath, '-s', "{$tvIp}:5555", 'shell', 'echo', 'hello']);
                $shellTestProcess->setTimeout(5);
                $shellTestProcess->run();
                $debug['shell_test_output'] = $shellTestProcess->getOutput();
                $debug['shell_test_success'] = $shellTestProcess->isSuccessful();
            } else {
                $debug['shell_test_success'] = false;
                $debug['shell_test_output'] = 'Device not connected';
            }

            // Add service debug info
            $debug['service_debug'] = $this->tvControlService->debugAdbPath();

        } catch (\Exception $e) {
            $debug['exception'] = $e->getMessage();
        }

        return response()->json($debug);
    }

    public function expired()
    {
        $expiredRentals = Rental::with(['cafeOrders.items.cafeItem']) // Load cafe orders for expired too
            ->where('status', 'completed')
            ->orWhere('end_time', '<', Carbon::now())
            ->orderBy('end_time', 'desc')
            ->get();

        return view('rentals.expired', compact('expiredRentals'));
    }

    public function deleteExpired(Rental $rental)
    {
        try {
            $rental->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function clearAllExpired()
    {
        try {
            $deleted = Rental::where('status', 'completed')
                ->orWhere('end_time', '<', Carbon::now())
                ->delete();
                
            return response()->json(['success' => true, 'deleted_count' => $deleted]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getActiveRentals()
    {
        try {
            $activeRentals = Rental::where('status', 'active')
                ->where('end_time', '>', Carbon::now())
                ->select('id', 'end_time', 'tv_ip', 'customer_name', 'ps_station')
                ->get();

            return response()->json([
                'success' => true,
                'rentals' => $activeRentals
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Rental $rental, Request $request)
    {
        try {
            $rental->update(['status' => $request->status]);
            
            return response()->json([
                'success' => true,
                'rental' => $rental
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getActiveRentalsPartial()
    {
        // Mark expired rentals as completed
        Rental::where('status', 'active')
            ->where('end_time', '<', Carbon::now())
            ->update(['status' => 'completed']);

        $activeRentals = Rental::where('status', 'active')
            ->where('end_time', '>', Carbon::now())
            ->orderBy('end_time', 'asc')
            ->get();

        return view('rentals._rentals_list', compact('activeRentals'))->render();
    }

    /**
     * Test ADB path accessibility
     */
    public function testAdbPath()
    {
        return response()->json($this->tvControlService->debugAdbPath());
    }

    /**
     * System status check
     */
    public function systemStatus()
    {
        $status = [
            'adb_available' => file_exists($this->adbPath),
            'active_rentals' => Rental::where('status', 'active')->count(),
            'tv_connections' => [
                '192.168.1.20' => $this->tvControlService->testConnection('192.168.1.20'),
                '192.168.1.21' => $this->tvControlService->testConnection('192.168.1.21')
            ],
            'timestamp' => now()->toISOString()
        ];

        return response()->json($status);
    }

    public function getActiveData()
    {
        $activeCount = Rental::where('status', 'active')->count();
        $lastUpdate = Rental::latest('updated_at')->first();
        
        return response()->json([
            'active_count' => $activeCount,
            'last_update' => $lastUpdate ? $lastUpdate->updated_at->toISOString() : null,
            'needsRefresh' => false // You can implement logic to determine if refresh is needed
        ]);
    }

}
