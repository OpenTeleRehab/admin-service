<?php

namespace App\Console\Commands;

use App\Models\SystemLimit;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Facades\Activity;

class ImportSystemLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:import-system-limit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import system limit value';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:import-system-limit';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Disable activity logging for system limit import
        Activity::disableLogging();

        $this->alert('Start importing system limit: ');
        $limitContent = Storage::get("system_limit/settings.json");
        $translateData = json_decode($limitContent, true) ?? [];

        $this->output->progressStart(count($translateData));
        foreach ($translateData as $key => $value) {
            $translateKeyPlatform = SystemLimit::where('content_type', $key)->first();
            if (!$translateKeyPlatform) {
                SystemLimit::create([
                    'content_type' => $key,
                    'value' => $value
                ]);
            }
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        // Re-enable activity logging after system limit import
        Activity::enableLogging();

        return 0;
    }
}
