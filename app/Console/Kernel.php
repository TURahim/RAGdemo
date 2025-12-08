<?php

namespace BookStack\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Send SOP review reminder emails daily at 8:00 AM
        // Also includes reminders for SOPs due within the next 7 days
        $schedule->command('bookstack:send-review-reminders --include-upcoming=7')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Weekly permission consistency audit (Sunday at 3:00 AM)
        // Automatically repairs any inconsistencies found
        $schedule->command('bookstack:audit-permissions --fix')
            ->weeklyOn(0, '03:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
