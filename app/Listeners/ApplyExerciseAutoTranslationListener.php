<?php

namespace App\Listeners;

use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyExerciseAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyExerciseAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyExerciseAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $exercise = $event->exercise;
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
            $autoTranslated = $exercise->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($exercise->title, $languageCode);
            $exercise->setTranslation('title', $languageCode, $translatedTitle);
            $exercise->setTranslation('auto_translated', $languageCode, true);

            foreach ($exercise->additionalFields as $additionalField) {
                $translatedField = $translate->translate($additionalField->field, $languageCode);
                $translatedValue = $translate->translate($additionalField->value, $languageCode);
                $additionalField->setTranslation('field', $languageCode, $translatedField);
                $additionalField->setTranslation('value', $languageCode, $translatedValue);
                $additionalField->save();
            }
        }
        $exercise->save();
    }
}
