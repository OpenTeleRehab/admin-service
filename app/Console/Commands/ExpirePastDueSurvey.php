<?php

namespace App\Console\Commands;

use App\Models\Survey;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpirePastDueSurvey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:expire-past-due-survey';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark surveys as expired if the end date has passed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Survey::whereDate('end_date', '<', Carbon::today())
            ->where('status', Survey::STATUS_PUBLISHED)
            ->update(['status' => Survey::STATUS_EXPIRED]);

        $this->info('Surveys marked as expired successfully!');

        return Command::SUCCESS;
    }
}
