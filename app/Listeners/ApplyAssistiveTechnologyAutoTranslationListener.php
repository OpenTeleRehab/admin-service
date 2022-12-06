<?php

namespace App\Listeners;

use App\Events\ApplyAssistiveTechnologyAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyAssistiveTechnologyAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyAssistiveTechnologyAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyAssistiveTechnologyAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $assistiveTechnology = $event->assistiveTechnology;
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
            $autoTranslated = $assistiveTechnology->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedName = $translate->translate($assistiveTechnology->name, $languageCode);
            $translatedDescription = $translate->translate($assistiveTechnology->description, $languageCode);
            $assistiveTechnology->setTranslation('name', $languageCode, $translatedName);
            $assistiveTechnology->setTranslation('description', $languageCode, $translatedDescription);
            $assistiveTechnology->setTranslation('auto_translated', $languageCode, true);
        }

        $assistiveTechnology->save();
    }
}
