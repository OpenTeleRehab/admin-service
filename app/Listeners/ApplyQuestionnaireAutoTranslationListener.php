<?php

namespace App\Listeners;

use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class ApplyQuestionnaireAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyQuestionnaireAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyQuestionnaireAutoTranslationEvent $event)
    {
        if (App::getLocale() !== config('app.fallback_locale')) {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $questionnaire = $event->questionnaire;
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
            $autoTranslated = $questionnaire->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            // Auto translate questionnaire.
            $translatedTitle = $translate->translate($questionnaire->title, $languageCode);
            $translatedDescription = $translate->translate($questionnaire->description, $languageCode);
            $questionnaire->setTranslation('title', $languageCode, $translatedTitle);
            $questionnaire->setTranslation('description', $languageCode, $translatedDescription);
            $questionnaire->setTranslation('auto_translated', $languageCode, true);

            // Auto translate question and answer.
            foreach ($questionnaire->questions as $question) {
                $translatedQuestionTitle = $translate->translate($question->title, $languageCode);
                $question->setTranslation('title', $languageCode, $translatedQuestionTitle);

                foreach ($question->answers as $answer) {
                    $translatedAnswerDescription = $translate->translate($answer->description, $languageCode);
                    $answer->setTranslation('description', $languageCode, $translatedAnswerDescription);
                    $answer->save();
                }

                $question->save();
            }
        }
        $questionnaire->save();
    }
}
