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
        $schedule->command('hi:sync-exercise-data')->dailyAt('0:00')->runInBackground();
        $schedule->command('hi:sync-education-material-data')->dailyAt('0:15')->runInBackground();
        $schedule->command('hi:sync-questionnaire-data')->dailyAt('0:30')->runInBackground();
        $schedule->command('hi:sync-category-data')->dailyAt('0:45')->runInBackground();
        $schedule->command('hi:sync-patient-data --all')->dailyAt('1:00')->runInBackground();
        $schedule->command('hi:sync-language-data')->dailyAt('1:15')->runInBackground();
        $schedule->command('hi:sync-assistive-technology-patient-data')->dailyAt('1:30')->runInBackground();
        $schedule->command('hi:sync-translation-data')->dailyAt('1:45')->runInBackground();
        $schedule->command('hi:sync-patient-twilio-call-data')->dailyAt('2:00')->runInBackground();
        $schedule->command('hi:sync-health-condition-data')->dailyAt('2:15')->runInBackground();
        $schedule->command('hi:clean-up-exported-files')->dailyAt('2:30')->runInBackground();
        $schedule->command('hi:sync-tutorial-data')->dailyAt('2:45')->runInBackground();
        $schedule->command('hi:expire-past-due-survey')->dailyAt('3:00')->runInBackground();
        $schedule->command('hi:sync-assistive-product-data')->dailyAt('3:15')->runInBackground();
        $schedule->command('hi:sync-faq-data')->dailyAt('3:30')->runInBackground();
        $schedule->command('hi:sync-screening-questionnaire-data')->dailyAt('3:45')->runInBackground();
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
