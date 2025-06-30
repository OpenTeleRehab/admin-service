<?php

namespace App\Listeners;

use App\Events\ApplyHealthConditionAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyHealthConditionAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyHealthConditionAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyHealthConditionAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $healthCondition = $event->healthCondition;
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
            $autoTranslated = $healthCondition->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($healthCondition->title, $languageCode);
            $healthCondition->setTranslation('title', $languageCode, $translatedTitle);
            $healthCondition->setTranslation('auto_translated', $languageCode, true);
        }
        $healthCondition->save();
    }
}
