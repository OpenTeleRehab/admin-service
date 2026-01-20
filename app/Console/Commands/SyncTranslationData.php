<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use Illuminate\Support\Facades\DB;

class SyncTranslationData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-translation-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync translation data from global to other organization';

    /**
     * @return void
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function handle()
    {
        if (env('APP_NAME') === 'hi') {
            $this->info('Skipping sync on global instance.');
            return;
        }

        $this->alert('Starting translation sync...');

        // Fetch translations from global
        $globalTranslations = GlobalDataSyncHelper::fetchData('get-translations');
        if (!$globalTranslations) {
            $this->error('Failed to fetch translations from global.');
            return;
        }

        $this->output->progressStart(count($globalTranslations));
        foreach ($globalTranslations as $globalTranslation) {
            // Upsert translation
            DB::table('translations')->updateOrInsert(
                ['id' => $globalTranslation->id],
                [
                    'key' => $globalTranslation->key,
                    'value' => $globalTranslation->value,
                    'platform' => $globalTranslation->platform,
                ]
            );
            
            // Upsert localizations
            if (isset($globalTranslation->localizations)) {
                foreach ($globalTranslation->localizations as $globalLocalization) {
                    DB::table('localizations')->updateOrInsert(
                        ['id' => $globalLocalization->id],
                        [
                            'translation_id' => $globalLocalization->translation_id,
                            'value' => $globalLocalization->value,
                            'language_id' => $globalLocalization->language_id,
                            'auto_translated' => $globalLocalization->auto_translated,
                        ]
                    );
                }
            }
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();

        $this->info('Translation sync completed successfully!');
}
}
