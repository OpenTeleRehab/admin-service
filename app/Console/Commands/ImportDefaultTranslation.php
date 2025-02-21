<?php

namespace App\Console\Commands;

use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use App\Models\Localization;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportDefaultTranslation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:import-default-translation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import default translation with key and value';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:import-default-translation';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $platforms = [
            Translation::ADMIN_PORTAL,
            Translation::THERAPIST_PORTAL,
            Translation::PATIENT_APP
        ];

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $errors = [];
        foreach ($platforms as $platform) {
            $this->alert('Start importing: ' . $platform);
            $localeContent = Storage::get("translation/$platform.json");
            $translateData = json_decode($localeContent, true) ?? [];

            $this->output->progressStart(count($translateData));
            foreach ($translateData as $key => $value) {
                $translateKeyPlatform = Translation::where('key', $key)->where('platform', $platform)->first();
                if (!$translateKeyPlatform) {
                    $translation = Translation::create([
                        'key' => $key,
                        'value' => $value,
                        'platform' => $platform
                    ]);

                    // Update other language(s) by using Google Translate.
                    $languages = Language::where('code', '!=', 'en')->get()->toArray();
                    foreach ($languages as $language) {
                        $languageCode = $language['code'];
                        if (!in_array($languageCode, $supportedLanguages)) {
                            continue;
                        }
                        try {
                            $translationValue = $translate->translate($translation->value, $languageCode);
                            Localization::create([
                                'translation_id' => $translation->id,
                                'language_id' => $language['id'],
                                'value' => $translationValue,
                                'auto_translated' => true,
                            ]);
                        } catch (\Exception $e) {
                            $errorCode = $e->getCode();
                            $errorMessage = $e->getMessage();
                    
                            // Ensure the same error (code + message) is not stored twice
                            $existError = collect($errors)->contains(fn($err) => $err['code'] === $errorCode && $err['message'] === $errorMessage);
                    
                            if (!$existError) {
                                $errors[] = ['code' => $errorCode, 'message' => $errorMessage];
                            }
                        }
                    }
                }
                $this->output->progressAdvance();
            }
            $this->output->progressFinish();
        }
        if (!empty($errors)) {
            $this->warn("Translation completed with errors:");
            $this->error(json_encode($errors, JSON_PRETTY_PRINT));
        }
        return 0;
    }
}
