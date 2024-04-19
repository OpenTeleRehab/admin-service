<?php

namespace App\Helpers;

use App\Models\EducationMaterial;
use App\Models\Exercise;
use App\Models\FavoriteActivitiesTherapist;
use App\Models\Questionnaire;
use App\Models\SystemLimit;

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
        $contentCount = Exercise::where('therapist_id', $therapistId)->where('parent_id', null)->count();
        $contentCount += EducationMaterial::where('therapist_id', $therapistId)->where('parent_id', null)->count();
        $contentCount += Questionnaire::where('therapist_id', $therapistId)->where('parent_id', null)->count();

        return $contentCount;
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
