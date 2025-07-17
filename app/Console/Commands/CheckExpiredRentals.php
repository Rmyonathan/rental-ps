<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Services\TvControlService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredRentals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rentals:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired rentals and play timeout video automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for expired rentals...');
        
        // Get TV control service
        $tvControlService = app(\App\Services\TvControlService::class);
        
        // Find expired rentals
        $expiredRentals = Rental::where('status', 'active')
            ->where('end_time', '<=', Carbon::now())
            ->get();

        if ($expiredRentals->isEmpty()) {
            $this->info('✅ No expired rentals found.');
            $this->showActiveRentals();
            return 0;
        }

        $this->warn("⚠️  Found {$expiredRentals->count()} expired rental(s):");
        
        foreach ($expiredRentals as $rental) {
            $this->processExpiredRental($rental, $tvControlService);
        }

        $this->info("\n🎯 Processing completed!");
        return 0;
    }

    private function processExpiredRental($rental, $tvControlService)
    {
        $this->line("\n📺 Processing: {$rental->customer_name} ({$rental->ps_station}) - TV: {$rental->tv_ip}");
        $this->line("   Expired: " . $rental->end_time->diffForHumans());
        
        // Execute timeout sequence - Play timeout video
        $this->info("   🎬 Playing timeout video...");
        
        $result = $tvControlService->executeTimeoutSequence($rental->tv_ip);
        
        if ($result['success']) {
            // Mark rental as completed
            $rental->update(['status' => 'completed']);
            
            $this->info("   ✅ SUCCESS: Timeout video playing for {$rental->customer_name}");
            $this->line("      - Connected to TV: {$rental->tv_ip}");
            $this->line("      - Timeout video is now playing");
            $this->line("      - Rental marked as completed");
            $this->line("      - Customer's PlayStation time has ended");
            
        } else {
            $this->error("   ❌ FAILED: {$rental->customer_name} - {$result['error']}");
            $this->line("      - Could not play timeout video");
            $this->line("      - Manual intervention required");
            $this->line("      - Check TV connection and ADB settings");
        }
    }

    private function showActiveRentals()
    {
        $activeRentals = Rental::where('status', 'active')->get();
        
        if ($activeRentals->isEmpty()) {
            $this->info('No active rentals at the moment. All PlayStation stations are available.');
            return;
        }

        $this->info("\n📋 Current active rentals:");
        
        foreach ($activeRentals as $rental) {
            $remainingTime = Carbon::now()->diffInMinutes($rental->end_time, false);
            
            if ($remainingTime > 0) {
                $timeLeft = $remainingTime > 60 
                    ? sprintf('%dh %dm', floor($remainingTime / 60), $remainingTime % 60)
                    : "{$remainingTime}m";
                    
                $this->line("   🎮 {$rental->customer_name} ({$rental->ps_station}) - {$timeLeft} remaining");
                $this->line("      TV: {$rental->tv_ip} | Ends: {$rental->end_time->format('H:i')}");
            }
        }
        
        $this->line("");
        $this->info("💡 Tip: These rentals are currently playing on HDMI 2 (PlayStation)");
        $this->info("💡 When they expire, timeout video will play automatically");
    }
}