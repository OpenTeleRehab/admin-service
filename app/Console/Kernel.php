<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('hi:sync-exercise-data')->runInBackground();
        $schedule->command('hi:sync-education-material-data')->runInBackground();
        $schedule->command('hi:sync-questionnaire-data')->runInBackground();
        $schedule->command('hi:sync-patient-data')->runInBackground();
        $schedule->command('hi:sync-assistive-technology-patient-data')->runInBackground();
        $schedule->command('hi:sync-patient-twilio-call-data')->runInBackground();
        $schedule->command('hi:clean-up-exported-files')->daily()->runInBackground();
        $schedule->command('hi:expire-past-due-survey')->runInBackground();
        $schedule->command('hi:update-treatment-plan-status')->daily()->runInBackground();
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
