<?php

namespace App\Listeners;

use App\Events\ApplyPrivacyPolicyAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;

class ApplyPrivacyPolicyAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyPrivacyPolicyAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyPrivacyPolicyAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $privacyPolicy = $event->privacyPolicy;
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
            $autoTranslated = $privacyPolicy->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedContent = $translate->translate($privacyPolicy->content, $languageCode);
            $privacyPolicy->setTranslation('content', $languageCode, $translatedContent);
            $privacyPolicy->setTranslation('auto_translated', $languageCode, true);
        }
        $privacyPolicy->save();
    }
}
