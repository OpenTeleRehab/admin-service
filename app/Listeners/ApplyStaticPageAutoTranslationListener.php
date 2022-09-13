<?php

namespace App\Listeners;

use App\Events\ApplyStaticPageAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyStaticPageAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyStaticPageAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyStaticPageAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $staticPage = $event->staticPage;
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
            $autoTranslated = $staticPage->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($staticPage->title, $languageCode);
            $translatedContent = $translate->translate($staticPage->content, $languageCode);
            $staticPage->setTranslation('title', $languageCode, $translatedTitle);
            $staticPage->setTranslation('content', $languageCode, $translatedContent);
            $staticPage->setTranslation('auto_translated', $languageCode, true);
        }
        $staticPage->save();
    }
}
