<?php

namespace App\Http\Resources;

use App\Helpers\ContentHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseListResource extends JsonResource
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
            'files' => FileResource::collection($this->files()->orderBy('order')->get()),
            'children' => ExerciseListResource::collection($this->children),
            'fallback' => [
                'title' => $this->getTranslation('title', config('app.fallback_locale')),
            ],
            'share_to_hi_library' => $this->share_to_hi_library,
            'therapist_id' => $this->therapist_id,
            'is_favorite' => ContentHelper::getFavoriteActivity($this, $request->get('therapist_id')),
            'auto_translated' => $this->auto_translated,
        ];
    }
}
