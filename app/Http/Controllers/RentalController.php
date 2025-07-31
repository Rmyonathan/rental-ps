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

    /**
     * Display the main dashboard with all configured TV stations and their rental status.
     */
    public function index()
    {
        // Get the list of all configured TV IPs from the Python server
        $configuredTvs = $this->tvControlService->getConfiguredTvs();
        
        if (empty($configuredTvs)) {
            // Fallback to a default list if the API is down to prevent a crash
            $configuredTvs = ['192.168.1.20', '192.168.1.21']; 
             session()->flash('warning', 'Could not connect to the TV control server. Please ensure it is running. Displaying a default list of TVs.');
        }

        $activeRentals = Rental::where('status', 'active')->with('cafeOrders')->get()->keyBy('tv_ip');

        // Create a collection of TV station objects for the view
        $tvStations = collect($configuredTvs)->map(function ($ip, $index) use ($activeRentals) {
            // Create a more descriptive station name
            $stationName = 'PS' . ($index + 1);
            $model = $this->tvControlService->getApiHealth()['configured_tvs'][$ip]['model'] ?? 'TV';
            $stationName .= ' (' . ucfirst($model) . ')';

            return (object)[
                'ip' => $ip,
                'station_name' => 'PS' . ($index + 1),
                'rental' => $activeRentals->get($ip)
            ];
        });
        
        return view('rentals.index', [
            'tvStations' => $tvStations,
            'activeRentals' => $activeRentals // For summary stats
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

        // Get all configured TVs and filter out those that are already rented
        $allTvs = $this->tvControlService->getConfiguredTvs();
        $activeRentalIps = Rental::where('status', 'active')->pluck('tv_ip')->toArray();
        $availableTvs = array_diff($allTvs, $activeRentalIps);

        return view('rentals.create', [
            'tv_ip' => $tvIp,
            'station' => $stationName,
            'availableTvs' => $availableTvs
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
        $query = Rental::where('tv_ip', $tv_ip)->orderBy('start_time', 'desc');
        $rentals = $query->get();
        $totalDuration = $rentals->sum('duration_minutes');
        $totalHours = round($totalDuration / 60, 2);

        return view('rentals.history', [
            'tv' => (object)['ip_address' => $tv_ip],
            'rentals' => $rentals,
            'totalDuration' => $totalDuration,
            'totalHours' => $totalHours,
        ]);
    }

    /**
     * Get a unique list of all TV IPs that have ever had a rental.
     */
    public function ipList()
    {
        $ips = Rental::select('tv_ip')->distinct()->get();
        return view('rentals.ip_list', compact('ips'));
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
}
