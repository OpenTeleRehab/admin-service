<?php

namespace App\Listeners;

use App\Events\ApplyHealthConditionGroupAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyHealthConditionGroupAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyHealthConditionGroupAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyHealthConditionGroupAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $healthConditionGroup = $event->healthConditionGroup;
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
            $autoTranslated = $healthConditionGroup->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($healthConditionGroup->title, $languageCode);
            $healthConditionGroup->setTranslation('title', $languageCode, $translatedTitle);
            $healthConditionGroup->setTranslation('auto_translated', $languageCode, true);
        }
        $healthConditionGroup->save();
    }
}
