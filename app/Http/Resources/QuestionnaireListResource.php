<?php

namespace App\Http\Resources;

use App\Helpers\ContentHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionnaireListResource extends JsonResource
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
            'description' => $this->description,
            'questions' => QuestionListResource::collection($this->questions),
            'fallback' => [
                'title' => $this->getTranslation('title', config('app.fallback_locale')),
                'description' => $this->getTranslation('description', config('app.fallback_locale')),
            ],
            'share_to_hi_library' => $this->share_to_hi_library,
            'therapist_id' => $this->therapist_id,
            'is_favorite' => ContentHelper::getFavoriteActivity($this, $request->get('therapist_id')),
        ];
    }
}
