<?php

namespace App\Listeners;

use App\Events\ApplyCategoryAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyCategoryAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyCategoryAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyCategoryAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $category = $event->category;
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
            $autoTranslated = $category->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($category->title, $languageCode);
            $category->setTranslation('title', $languageCode, $translatedTitle);
            $category->setTranslation('auto_translated', $languageCode, true);
        }
        $category->save();
    }
}
