<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Evaluate SMS campaigns and queue messages every 15 minutes
        $schedule->job(new \App\Jobs\EvaluateSmsCampaignsJob)->everyFifteenMinutes();

        // Process queued SMS messages every 5 minutes
        $schedule->job(new \App\Jobs\SendQueuedSmsJob(100))->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
