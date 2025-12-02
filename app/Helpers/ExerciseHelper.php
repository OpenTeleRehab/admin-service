<?php

namespace App\Helpers;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ExerciseHelper
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public static function generateFilterQuery(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $query = Exercise::select('exercises.*')->where('exercises.parent_id', null);
        $filter = json_decode($request->get('filter'), true);

        if (!empty($filter['favorites_only'])) {
            $query->join('favorite_activities_therapists', function ($join) use ($therapistId) {
                $join->on('exercises.id', 'favorite_activities_therapists.activity_id');
            })->where('favorite_activities_therapists.therapist_id', $therapistId)
                ->where('favorite_activities_therapists.type', 'exercises')
                ->where('favorite_activities_therapists.is_favorite', true);
        }

        if (!empty($filter['my_contents_only'])) {
            $query->where('exercises.therapist_id', $therapistId);
        }

        if (!empty($filter['suggestions'])) {
            $query->whereHas('children');
        }

        $query->where(function ($query) use ($therapistId) {
            $query->whereNull('exercises.therapist_id');
            if ($therapistId) {
                $query->orWhere('exercises.therapist_id', $therapistId);
            }
        });

        if (!empty($filter['search_value'])) {
            $locale = App::getLocale();
            $query->whereRaw("JSON_EXTRACT(LOWER(title), \"$.$locale\") LIKE ?", ['%' . strtolower($filter['search_value']) . '%']);
        }

        if ($request->get('categories')) {
            $categories = $request->get('categories');
            foreach ($categories as $category) {
                $query->whereHas('categories', function ($query) use ($category) {
                    $query->where('categories.id', $category);
                });
            }
        }

        return $query;
    }
}
