<?php

namespace App\Listeners;

use App\Events\ApplyExerciseAutoTranslationEvent;
use App\Events\ApplyMaterialAutoTranslationEvent;
use App\Events\ApplyNewLanguageTranslationEvent;
use App\Events\ApplyQuestionnaireAutoTranslationEvent;
use App\Models\EducationMaterial;
use App\Models\Exercise;
use App\Models\Questionnaire;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplyNewLanguageTranslationListener implements ShouldQueue
{

    /**
     * Handle the event.
     *
     * @param  ApplyNewLanguageTranslationEvent  $event
     * @return void
     */
    public function handle(ApplyNewLanguageTranslationEvent $event)
    {
        $langCode = $event->langCode;
        $exercises = Exercise::where('status', 'approved')->get();
        foreach ($exercises as $exercise) {
            event(new ApplyExerciseAutoTranslationEvent($exercise, $langCode));
        }

        $educationMaterials = EducationMaterial::where('status', 'approved')->get();
        foreach ($educationMaterials as $educationMaterial) {
            event(new ApplyMaterialAutoTranslationEvent($educationMaterial, $langCode));
        }

        $questionnaires = Questionnaire::where('status', 'approved')->get();
        foreach ($questionnaires as $questionnaire) {
            event(new ApplyQuestionnaireAutoTranslationEvent($questionnaire, $langCode));
        }
    }
}
