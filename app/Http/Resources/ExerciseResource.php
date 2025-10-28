<?php

namespace App\Http\Resources;

use App\Helpers\ContentHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'sets' => $this->sets,
            'reps' => $this->reps,
            'include_feedback' => $this->include_feedback,
            'get_pain_level' => $this->get_pain_level,
            'files' => FileResource::collection($this->files()->orderBy('order')->get()),
            'categories' => $this->categories ? $this->categories->pluck('id') : [],
            'therapist_id' => $this->therapist_id,
            'is_favorite' => ContentHelper::getFavoriteActivity($this, $request->get('therapist_id')),
            'additional_fields' => AdditionalFieldResource::collection($this->additionalFields),
            'global' => $this->global,
            'auto_translated' => $this->auto_translated,
            'parent_id' => $this->parent_id,
            'children' => ExerciseResource::collection($this->children),
            'share_to_hi_library' => $this->share_to_hi_library,
            'suggested_lang' => $this->suggested_lang,
            'fallback' => [
                'title' => $this->getTranslation('title', config('app.fallback_locale')),
            ],
            'share_with_phc_worker' => $this->share_with_phc_worker,
        ];
    }
}
