<?php

namespace App\Listeners;

use App\Events\ApplyTranslationAutoTranslationEvent;
use App\Helpers\GoogleTranslateHelper;
use App\Models\Language;
use App\Models\Localization;
use Illuminate\Support\Facades\App;

class ApplyTranslationAutoTranslationListener
{
    /**
     * Handle the event.
     *
     * @param ApplyTranslationAutoTranslationEvent $event
     *
     * @return void
     */
    public function handle(ApplyTranslationAutoTranslationEvent $event)
    {
        if (App::getLocale() !== 'en') {
            return;
        }

        $translate = new GoogleTranslateHelper();
        $supportedLanguages = $translate->supportedLanguages();
        $translation = $event->translation;
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
            $hasManualTranslated = Localization::where('translation_id', $translation->id)
                ->where('language_id', $language->id)
                ->where('auto_translated', '<>', true)->count();

            if ($hasManualTranslated) {
                continue;
            }

            // Create placeholders for skipped words.
            $placeholders = ['&#39;' => '\''];
            $count = 0;

            // Replace words to skip with placeholders.
            $pattern = '/\$\{[^}]*\}/';
            $modifiedText = preg_replace_callback($pattern, function ($matches) use (&$placeholders, &$count) {
                $placeholder = '{' . $count . '}';
                $placeholders[$placeholder] = $matches[0];
                $count++;
                return $placeholder;
            }, $translation->value);

            $translatedText = $translate->translate($modifiedText, $languageCode);

            // Replace placeholders with original words.
            $translatedText = str_replace(array_keys($placeholders), array_values($placeholders), $translatedText);

            Localization::create([
                'translation_id' => $translation->id,
                'language_id' => $language->id,
                'value' => $translatedText,
                'auto_translated' => true,
            ]);
        }
    }
}
