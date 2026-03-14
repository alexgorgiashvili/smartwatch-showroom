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
        // Process queue jobs every minute (for database queue without Supervisor)
        $schedule->command('queue:work --stop-when-empty --max-time=50')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Cleanup failed jobs older than 48 hours
        $schedule->command('queue:prune-failed --hours=48')
                 ->daily();

        // Cleanup old queue jobs (if any stuck)
        $schedule->command('queue:prune-batches --hours=48')
                 ->daily();
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
