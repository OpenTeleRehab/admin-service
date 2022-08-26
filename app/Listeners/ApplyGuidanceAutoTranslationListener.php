<?php

namespace App\Listeners;

use App\Events\ApplyGuidanceAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyGuidanceAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyGuidanceAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyGuidanceAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $guidance = $event->guidance;
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
            $autoTranslated = $guidance->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($guidance->title, $languageCode);
            $translatedContent = $translate->translate($guidance->content, $languageCode);
            $guidance->setTranslation('title', $languageCode, $translatedTitle);
            $guidance->setTranslation('content', $languageCode, $translatedContent);
            $guidance->setTranslation('auto_translated', $languageCode, true);
        }
        $guidance->save();
    }
}
