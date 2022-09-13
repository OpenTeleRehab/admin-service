<?php

namespace App\Listeners;

use App\Events\ApplyTermAndConditionAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyTermAndConditionAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyTermAndConditionAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyTermAndConditionAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $termAndCondition = $event->termAndCondition;
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
            $autoTranslated = $termAndCondition->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedContent = $translate->translate($termAndCondition->content, $languageCode);
            $termAndCondition->setTranslation('content', $languageCode, $translatedContent);
            $termAndCondition->setTranslation('auto_translated', $languageCode, true);
        }
        $termAndCondition->save();
    }
}
