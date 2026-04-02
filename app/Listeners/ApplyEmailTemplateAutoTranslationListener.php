<?php

namespace App\Listeners;

use App\Events\ApplyEmailTemplateAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use Illuminate\Support\Facades\App;
use Spatie\Activitylog\Facades\Activity;

class ApplyEmailTemplateAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyEmailTemplateAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyEmailTemplateAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        // Disable activity logging
        Activity::disableLogging();

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $emailTemplate = $event->emailTemplate;
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
            $autoTranslated = $emailTemplate->getTranslation('auto_translated', $languageCode);
            if ($autoTranslated === false) {
                continue;
            }

            $translatedTitle = $translate->translate($emailTemplate->title, $languageCode);
            $translatedContent = $translate->translate($emailTemplate->content, $languageCode);
            $emailTemplate->setTranslation('title', $languageCode, $translatedTitle);
            $emailTemplate->setTranslation('content', $languageCode, $translatedContent);
            $emailTemplate->setTranslation('auto_translated', $languageCode, true);
        }

        $emailTemplate->save();

        // Re-enable activity logging
        Activity::enableLogging();
    }
}
