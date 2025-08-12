<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\TvControlService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RentalController extends Controller
{
    protected $tvControlService;

    public function __construct(TvControlService $tvControlService)
    {
        $this->tvControlService = $tvControlService;
    }

    // // NEW: Add this private helper function to manage all station data.
    private function getStationData()
    {
        // This is the central map for all your stations.
        // Edit names, consoles, and prices here.
        $stationMap = [
            '192.168.1.20' => ['name' => '3 (PS5)', 'console' => 'PS5', 'price' => 20000, 'type' => 'Regular'],
            '192.168.1.33' => ['name' => '1 (PS4)', 'console' => 'PS4', 'price' => 10000, 'type' => 'Regular'],
            '192.168.1.35' => ['name' => '2 (PS4)', 'console' => 'PS4', 'price' => 10000, 'type' => 'Regular'],
            '192.168.1.38' => ['name' => '4 (PS4)', 'console' => 'PS4', 'price' => 10000, 'type' => 'Regular'],
            '192.168.1.37' => ['name' => '5 (PS4)', 'console' => 'PS4', 'price' => 10000, 'type' => 'Regular'],
            '192.168.1.39' => ['name' => '6 (PS5)', 'console' => 'PS5', 'price' => 20000, 'type' => 'Regular'],
            '192.168.1.40' => ['name' => 'VIP 1 Alpha', 'console' => 'PS4 + Switch', 'price' => 30000, 'type' => 'VIP'],
            '192.168.1.31' => ['name' => 'VIP 2 Beta', 'console' => 'PS4 + Switch', 'price' => 30000, 'type' => 'VIP'],
            '192.168.1.34' => ['name' => 'VVIP 1 Delta', 'console' => 'PS5 + Switch', 'price' => 40000, 'type' => 'VVIP'],
            '192.168.1.36' => ['name' => 'VVIP 2 Gamma', 'console' => 'PS5 + Switch', 'price' => 40000, 'type' => 'VVIP'],
            '192.168.1.99' => ['name' => 'Test TV', 'console' => 'Test', 'price' => 1000, 'type' => 'Test'],
        ];

        // Get all configured IPs from the Python service
        $configuredIps = $this->tvControlService->getConfiguredTvs();
        
        $stations = [];
        foreach ($configuredIps as $ip) {
            if (isset($stationMap[$ip])) {
                $stations[] = (object)[
                    'ip' => $ip,
                    'station_name' => $stationMap[$ip]['name'],
                    'console' => $stationMap[$ip]['console'],
                    'price_per_hour' => $stationMap[$ip]['price'],
                    'type' => $stationMap[$ip]['type']
                ];
            }
        }
        return collect($stations);
    }

    /**
     * Display the main dashboard with all configured TV stations and their rental status.
     */
    public function index()
    {
        $allStations = $this->getStationData();
        $activeRentals = Rental::where('status', 'active')->with('cafeOrders')->get()->keyBy('tv_ip');

        // Define the custom sort order for station types
        $typeOrder = [
            'Regular' => 1,
            'VIP' => 2,
            'VVIP' => 3,
            'Test' => 4,
        ];

        // // EDIT: Add this sorting logic before passing the data to the view.
        $tvStations = $allStations->sortBy(function ($station) use ($typeOrder) {
            // Get the primary sort key from the type order map
            $primarySort = $typeOrder[$station->type] ?? 99;
            
            // For 'Regular' stations, extract the number for secondary sorting
            $secondarySort = 0;
            if ($station->type === 'Regular') {
                preg_match('/^\d+/', $station->station_name, $matches);
                $secondarySort = isset($matches[0]) ? (int)$matches[0] : 0;
            }

            // Return a combined key for sorting
            return $primarySort . '.' . str_pad($secondarySort, 4, '0', STR_PAD_LEFT);
        });

        // Attach rental data to each station object
        $tvStations->each(function ($station) use ($activeRentals) {
            $station->rental = $activeRentals->get($station->ip);
        });
        
        return view('rentals.index', [
            'tvStations' => $tvStations,
            'activeRentals' => $activeRentals
        ]);
    }

    /**
     * NEW: Efficiently refresh the status of all TVs.
     */
    public function refreshAllStatuses()
    {
        $results = $this->tvControlService->testAllConnections();
        return response()->json($results);
    }

    /**
     * Show the form for creating a new rental.
     */
    public function create(Request $request)
{
    $tvIp = $request->input('tv_ip');
    $stationName = $request->input('station');

    // // This is the new helper function from the previous step
    $allStations = $this->getStationData(); 
    $activeRentalIps = Rental::where('status', 'active')->pluck('tv_ip')->toArray();

    // Filter out the stations that are currently rented
    $availableTvs = $allStations->whereNotIn('ip', $activeRentalIps);

    return view('rentals.create', [
        'tv_ip' => $tvIp,
        'station' => $stationName,
        'availableTvs' => $availableTvs // This is now a collection of objects
    ]);
}

    /**
     * Store a newly created rental in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'tv_ip' => 'required|ip',
            'ps_station' => 'required|string',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        // 1. Switch TV to HDMI before creating the rental record
        $switchResult = $this->tvControlService->switchToHdmi($validated['tv_ip']);
        if (!$switchResult['success']) {
            return back()->with('warning', 'Failed to switch TV to HDMI input. Please check the TV and try again. Error: ' . $switchResult['error'])->withInput();
        }

        // 2. Create the rental record
        $rental = Rental::create([
            'customer_name' => $validated['customer_name'],
            'tv_ip' => $validated['tv_ip'],
            'ps_station' => $validated['ps_station'],
            'duration_minutes' => $validated['duration_minutes'],
            'price' => $validated['price'],
            'start_time' => now(),
            'end_time' => now()->addMinutes((int) $validated['duration_minutes']),
            'status' => 'active',
        ]);
        
        // 3. Record the transaction for accounting
        TransactionService::recordRentalTransaction($rental);

        // 4. Start the timeout monitor on the Python server
        $this->tvControlService->startRentalMonitor($rental->tv_ip, $rental->id, $rental->duration_minutes * 60);

        return redirect()->route('rentals.index')->with('success', 'Rental for ' . $rental->customer_name . ' started successfully!');
    }
    
    /**
     * Forcefully end a rental immediately and play the timeout video.
     */
    public function forceTimeout($rentalId)
    {
        $rental = Rental::findOrFail($rentalId);
        
        // 1. Tell the Python server to play the video
        $result = $this->tvControlService->playTimeoutVideo($rental->tv_ip, $rental->id);
        
        // 2. Stop the server-side monitor for this rental
        $this->tvControlService->stopRentalMonitor($rental->id);

        // 3. Update the rental status in the database
        $rental->update(['status' => 'completed', 'end_time' => now()]);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Timeout video started and rental marked as completed.']);
        }

        // Return a message indicating video playback failed but the rental was still ended
        return response()->json([
            'success' => false, 
            'message' => 'Rental completed, but failed to play timeout video: ' . $result['error']
        ]);
    }

    /**
     * Handle generic TV control commands (volume, power).
     */
    public function controlTv(Request $request)
    {
        $validated = $request->validate([
            'tv_ip' => 'required|ip',
            'action' => 'required|string|in:volume_up,volume_down,power_off',
        ]);

        $result = $this->tvControlService->sendControl($validated['tv_ip'], $validated['action']);
        return response()->json($result);
    }
    
    /**
     * Test the ADB connection to a specific TV.
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate(['tv_ip' => 'required|ip']);
        $result = $this->tvControlService->testConnection($validated['tv_ip']);
        return response()->json($result);
    }

    /**
     * Extend the duration of an active rental.
     */
    public function extend(Request $request, $rentalId)
{
    $rental = Rental::findOrFail($rentalId);
    $validated = $request->validate([
        'additional_minutes' => 'required|integer|min:1',
        'additional_price' => 'required|numeric|min:0',
    ]);

    // FIX: Use the existing Carbon instance and cast the input to an integer
    $rental->end_time = $rental->end_time->addMinutes((int) $validated['additional_minutes']);
    
    $rental->duration_minutes += (int) $validated['additional_minutes'];
    $rental->price += $validated['additional_price'];
    $rental->save();
    
    // Stop the old monitor and start a new one with the updated duration
    $newDurationSeconds = now()->diffInSeconds($rental->end_time);
    $this->tvControlService->startRentalMonitor($rental->tv_ip, $rental->id, $newDurationSeconds);

    return response()->json(['success' => true, 'message' => 'Rental extended successfully.']);
}

    /**
     * Mark a rental as completed manually.
     */
    public function complete(Request $request, $rentalId)
    {
        $rental = Rental::findOrFail($rentalId);
        $rental->update(['status' => 'completed']);
        
        // Stop the server-side monitor
        $this->tvControlService->stopRentalMonitor($rental->id);
        
        return response()->json(['success' => true, 'message' => 'Rental marked as completed.']);
    }

    /**
     * Display a paginated list of expired/completed rentals.
     */
    public function expired()
    {
        $expiredRentals = Rental::where('status', '!=', 'active')
            ->orderBy('end_time', 'desc')
            ->with('cafeOrders')
            ->paginate(9); // 9 per page for a 3x3 grid
        return view('rentals.expired', compact('expiredRentals'));
    }
    
    /**
     * Show detailed information for a single rental (used for modals).
     */
    public function show(Rental $rental)
    {
        $rental->load('cafeOrders.items.cafeItem');
        return view('rentals.show', compact('rental'));
    }
    
    /**
     * Get the rental history for a specific TV IP address.
     */
    public function historyByIp(Request $request, $tv_ip)
    {
        // Fetch all station data to find the current station's name
        $allStations = $this->getStationData();
        $currentStation = $allStations->firstWhere('ip', $tv_ip);

        // If for some reason the IP is not in our map, create a fallback object
        if (!$currentStation) {
            $currentStation = (object)['ip' => $tv_ip, 'station_name' => 'Unknown Station'];
        }

        $query = Rental::where('tv_ip', $tv_ip)->orderBy('start_time', 'desc');

        // Apply search filter for customer name
        if ($request->filled('search')) {
            $query->where('customer_name', 'like', '%' . $request->search . '%');
        }

        // Apply date range filters
        if ($request->filled('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        // Get filtered results for totals
        $filteredRentals = $query->get();
        $totalDuration = $filteredRentals->sum('duration_minutes');
        $totalPrice = $filteredRentals->sum('price');
        $totalHours = round($totalDuration / 60, 2);

        // Paginate the results
        $rentals = $query->paginate(15);

        return view('rentals.history', [
            'tv' => $currentStation, // Pass the full station object
            'rentals' => $rentals,
            'totalDuration' => $totalDuration,
            'totalPrice' => $totalPrice,
            'totalHours' => $totalHours,
        ]);
    }
    /**
     * Get a unique list of all TV IPs that have ever had a rental.
     */
    public function ipList()
    {
        $allStations = $this->getStationData();

        // Define the custom sort order for station types
        $typeOrder = [
            'Regular' => 1,
            'VIP'     => 2,
            'VVIP'    => 3,
            'Test'    => 4,
        ];

        // Apply the same sorting logic as the index page
        $stations = $allStations->sortBy(function ($station) use ($typeOrder) {
            $primarySort = $typeOrder[$station->type] ?? 99;
            
            $secondarySort = 0;
            if ($station->type === 'Regular') {
                preg_match('/^\d+/', $station->station_name, $matches);
                $secondarySort = isset($matches[0]) ? (int)$matches[0] : 0;
            }

            return $primarySort . '.' . str_pad($secondarySort, 4, '0', STR_PAD_LEFT);
        });
        
        // Pass the sorted collection of station objects to the view.
        return view('rentals.ip_list', ['stations' => $stations]);
    }
    /**
     * Update a rental's end time. Used for syncing countdowns after extending.
     */
    public function updateEndTime(Request $request)
    {
        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'new_end_time' => 'required|date'
        ]);
        
        $rental = Rental::find($validated['rental_id']);
        $rental->end_time = $validated['new_end_time'];
        $rental->save();
        
        return response()->json(['success' => true]);
    }
    // // NEW: Add this function to handle switching HDMI inputs.
    public function switchHdmi(Request $request)
    {
        $validated = $request->validate([
            'tv_ip' => 'required|ip',
            'hdmi_input' => 'required|string|in:hdmi1,hdmi2',
        ]);

        $result = $this->tvControlService->switchHdmiInput(
            $validated['tv_ip'],
            $validated['hdmi_input']
        );

        return response()->json($result);
    }

    // // NEW: Add this function to handle getting the HDMI status.
    public function getHdmiStatus(Request $request)
    {
        $validated = $request->validate(['tv_ip' => 'required|ip']);

        $result = $this->tvControlService->getHdmiStatus($validated['tv_ip']);

        return response()->json($result);
    }
}
