<?php

namespace App\Helpers;

use App\Models\EducationMaterial;
use App\Models\Exercise;
use App\Models\FavoriteActivitiesTherapist;
use App\Models\Questionnaire;
use App\Models\SystemLimit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @package App\Helpers
 */
class ContentHelper
{
    /**
     * @param boolean $favorite
     * @param integer $therapistId
     * @param \App\Models\Exercise|\App\Models\EducationMaterial|\App\Models\Questionnaire $activity
     *
     * @return void
     */
    public static function flagFavoriteActivity($favorite, $therapistId, $activity)
    {
        FavoriteActivitiesTherapist::updateOrCreate(
            [
                'type' => $activity->getTable(),
                'activity_id' => $activity->id,
                'therapist_id' => $therapistId,
            ],
            [
                'is_favorite' => $favorite,
            ]
        );
    }

    /**
     * @param mixed $activity
     * @param integer $therapistId
     * @return int
     */
    public static function getFavoriteActivity($activity, $therapistId)
    {
        return FavoriteActivitiesTherapist::where('is_favorite', true)
            ->where('therapist_id', $therapistId)
            ->where('activity_id', $activity->id)
            ->where('type', $activity->getTable())
            ->count();
    }

    /**
     * @param integer $therapistId
     *
     * @return integer
     */
    public static function countTherapistContents($therapistId)
    {
        $contentCount = Exercise::where('therapist_id', $therapistId)->count();
        $contentCount += EducationMaterial::where('therapist_id', $therapistId)->count();
        $contentCount += Questionnaire::where('therapist_id', $therapistId)->count();

        return $contentCount;
    }

    /**
     * @param integer $therapistId
     *
     * @return integer
     */
    public static function deleteTherapistContents($therapistId)
    {
        $exerciseIds = [];
        $materialIds = [];
        $questionnaireIds = [];

        $ownExercises = Exercise::where('therapist_id', $therapistId)->get();
        $ownMaterials = EducationMaterial::where('therapist_id', $therapistId)->get();
        $ownQuestionnaires = Questionnaire::where('therapist_id', $therapistId)->get();

        foreach ($ownExercises as $ownExercise) {
            array_push($exerciseIds, $ownExercise->id);
        }

        foreach ($ownMaterials as $ownMaterial) {
            array_push($materialIds, $ownMaterial->id);
        }

        foreach ($ownQuestionnaires as $ownQuestionnaire) {
            array_push($questionnaireIds, $ownQuestionnaire->id);
        }

        if ($exerciseIds) {
            Http::post(env('PATIENT_SERVICE_URL') . '/activities/delete/by-ids', [
                'activity_ids' => $exerciseIds,
                'type' => 'exercise'
            ]);
        }
        if ($materialIds) {
            Http::post(env('PATIENT_SERVICE_URL') . '/activities/delete/by-ids', [
                'activity_ids' => $materialIds,
                'type' => 'material'
            ]);
        }
        if ($questionnaireIds) {
            Http::post(env('PATIENT_SERVICE_URL') . '/activities/delete/by-ids', [
                'activity_ids' => $questionnaireIds,
                'type' => 'questionnaire'
            ]);
        }

        Exercise::where('therapist_id', $therapistId)->delete();
        EducationMaterial::where('therapist_id', $therapistId)->delete();
        Questionnaire::where('therapist_id', $therapistId)->delete();

        return true;
    }

    /**
     * @param string $type
     * @return int
     */
    public static function getContentLimitLibray($type)
    {
        $systemLimit = SystemLimit::where('content_type', $type)->first();

        return $systemLimit ? $systemLimit->value : 0;
    }
}
