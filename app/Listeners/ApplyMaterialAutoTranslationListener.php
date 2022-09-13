<?php

namespace App\Listeners;

use App\Events\ApplyMaterialAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyMaterialAutoTranslationListener
{

    /**
     * Handle the event.
     *
     * @param ApplyMaterialAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyMaterialAutoTranslationEvent $event)
    {
        if (App::getLocale() !== config('app.fallback_locale')) {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $educationMaterial = $event->educationMaterial;
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
            $autoTranslated = $educationMaterial->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($educationMaterial->title, $languageCode);
            $educationMaterial->setTranslation('title', $languageCode, $translatedTitle);
            $educationMaterial->setTranslation('auto_translated', $languageCode, true);
        }
        $educationMaterial->save();
    }
}
