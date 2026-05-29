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

        // Fetch social media comments from Meta Graph API
        $schedule->command('social:fetch-comments --hours=2')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('social:process-instagram-publishes --limit=25')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Publish posts whose scheduled_at time has arrived
        $schedule->command('social:publish-scheduled')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Fetch engagement metrics for published posts (daily at 6 AM)
        $schedule->command('social:fetch-insights')
                 ->dailyAt('06:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Facebook competitor scraping (daily at 3 AM) + AI relevance filter
        $schedule->command('competitors:scrape-facebook --analyze')
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Facebook competitor weekly analysis (Sunday at 4 AM)
        $schedule->command('competitors:scrape-facebook --weekly-analysis')
                 ->weeklyOn(0, '04:00')
                 ->withoutOverlapping()
                 ->runInBackground();
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
