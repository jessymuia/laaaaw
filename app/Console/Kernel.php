<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // FUN-3: hearing-date reminders. Runs once daily; reminds attorneys
        // and clients 1 day ahead of a hearing. `reminder_sent_at` on the
        // hearings table guarantees this never double-sends even if the
        // scheduler fires more than once for the same day.
        $schedule->command('hearings:send-reminders --days=1')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        // ENG-6: nightly database + document backup (see
        // docs/ops/BACKUP_STRATEGY.md and app/Console/Commands/RunBackup.php).
        // Runs at 02:00 — after normal business hours, before the 08:00
        // reminder job above — so a slow dump never contends with either.
        $schedule->command('backup:run')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onFailure(function () {
                logger()->critical('Nightly backup (backup:run) failed — see scheduler log for the underlying error.');
            });
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
