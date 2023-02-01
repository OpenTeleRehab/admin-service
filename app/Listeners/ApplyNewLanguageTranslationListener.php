<?php

namespace App\Listeners;

use App\Events\ApplyCategoryAutoTranslationEvent;
use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Events\ApplyGuidanceAutoTranslationEvent;
use App\Events\ApplyMaterialAutoTranslationEvent;
use App\Events\ApplyNewLanguageTranslationEvent;
use App\Events\ApplyPrivacyPolicyAutoTranslationEvent;
use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Events\ApplyStaticPageAutoTranslationEvent;
use App\Events\ApplyTermAndConditionAutoTranslationEvent;
use App\Events\ApplyTranslationAutoTranslationEvent;
use App\Models\Category;
use App\Models\EducationMaterial;
use App\Models\Exercise;
use App\Models\Guidance;
use App\Models\PrivacyPolicy;
use App\Models\Questionnaire;
use App\Models\StaticPage;
use App\Models\TermAndCondition;
use App\Models\Translation;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplyNewLanguageTranslationListener implements ShouldQueue
{
    /**
     * The names of the queues to work.
     *
     * @var string
     */
    public $queue = 'high';

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var integer
     */
    public $timeout = 3600;

    /**
     * Handle the event.
     *
     * @param  ApplyNewLanguageTranslationEvent  $event
     * @return void
     */
    public function handle(ApplyNewLanguageTranslationEvent $event)
    {
        $langCode = $event->langCode;
        $exercises = Exercise::where('therapist_id', null)->get();
        foreach ($exercises as $exercise) {
            event(new ApplyExerciseAutoTranslationEvent($exercise, $langCode));
        }

        $educationMaterials = EducationMaterial::where('therapist_id', null)->get();
        foreach ($educationMaterials as $educationMaterial) {
            event(new ApplyMaterialAutoTranslationEvent($educationMaterial, $langCode));
        }

        $questionnaires = Questionnaire::where('therapist_id', null)->get();
        foreach ($questionnaires as $questionnaire) {
            event(new ApplyQuestionnaireAutoTranslationEvent($questionnaire, $langCode));
        }

        $categories = Category::all();
        foreach ($categories as $category) {
            event(new ApplyCategoryAutoTranslationEvent($category, $langCode));
        }

        $guidances = Guidance::all();
        foreach ($guidances as $guidance) {
            event(new ApplyGuidanceAutoTranslationEvent($guidance, $langCode));
        }

        $privacyPolicies = PrivacyPolicy::all();
        foreach ($privacyPolicies as $privacyPolicy) {
            event(new ApplyPrivacyPolicyAutoTranslationEvent($privacyPolicy, $langCode));
        }

        $staticPages = StaticPage::all();
        foreach ($staticPages as $staticPage) {
            event(new ApplyStaticPageAutoTranslationEvent($staticPage, $langCode));
        }

        $termAndConditions = TermAndCondition::all();
        foreach ($termAndConditions as $termAndCondition) {
            event(new ApplyTermAndConditionAutoTranslationEvent($termAndCondition, $langCode));
        }

        $translations = Translation::all();
        foreach ($translations as $translation) {
            event(new ApplyTranslationAutoTranslationEvent($translation, $langCode));
        }
    }
}
