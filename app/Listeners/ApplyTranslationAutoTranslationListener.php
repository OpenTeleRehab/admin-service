<?php

namespace App\Listeners;

use App\Events\ApplyTranslationAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use App\Models\Localization;
use Illuminate\Support\Facades\App;

class ApplyTranslationAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyTranslationAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyTranslationAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $translation = $event->translation;
        $langCode = $event->langCode;
        $languageQuery = Language::where('code', '<>', config('app.fallback_locale'));
        if ($langCode) {
            $languageQuery->where('code', $langCode);
        }
        $languages = $languageQuery->get();
        foreach ($languages as $language) {
            $languageCode = $language->code;
            if (!in_array($languageCode, $supportedLanguages)) {
                continue;
            }

            // Do not override the static translation.
            $hasManualTranslated = Localization::where('translation_id', $translation->id)
                ->where('language_id', $language->id)
                ->where('auto_translated', '<>', true)->count();

            if ($hasManualTranslated) {
                continue;
            }

            $translationValue = $translate->translate($translation->value, $languageCode);
            Localization::create([
                'translation_id' => $translation->id,
                'language_id' => $language->id,
                'value' => $translationValue,
                'auto_translated' => true,
            ]);
        }
    }
}
