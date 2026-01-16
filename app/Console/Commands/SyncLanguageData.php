<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\GlobalDataSyncHelper;
use App\Models\Language;
use Illuminate\Support\Facades\DB;

class SyncLanguageData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:sync-language-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync language data from global to other organization';

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

        $this->alert('Starting language sync...');

        // Fetch languages from global
        $globalLanguages = GlobalDataSyncHelper::fetchData('get-languages');
        if (!$globalLanguages) {
            $this->error('Failed to fetch languages from global.');
            return;
        }

        // Collect all global language IDs for deletion check
        $globalLanguageIds = collect($globalLanguages)->pluck('id')->toArray();
        $this->output->progressStart(count($globalLanguages));
        foreach ($globalLanguages as $globalLanguage) {
            // Upsert language
            DB::table('languages')->updateOrInsert(
                ['id' => $globalLanguage->id],
                [
                    'name' => $globalLanguage->name,
                    'code' => $globalLanguage->code,
                    'rtl' => $globalLanguage->rtl,
                    'auto_translated' => $globalLanguage->auto_translated,
                ]
            );
            $this->output->progressAdvance();
        }

        // Delete languages that no longer exist in the global languages
        Language::whereNotIn('id', $globalLanguageIds)
            ->delete();
        $this->output->progressFinish();

        $this->info('Language sync completed successfully!');
}
}
