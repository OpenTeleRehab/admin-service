<?php

namespace App\Http\Resources;

use App\Helpers\ContentHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class EducationMaterialResource extends JsonResource
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
            'fallback' => [
                'title' => $this->getTranslation('title', config('app.fallback_locale')),
            ],
            'file' => $this->file_id_no_fallback ? new FileResource($this->file) : null,
            'share_to_hi_library' => $this->share_to_hi_library,
            'therapist_id' => $this->therapist_id,
            'is_favorite' => ContentHelper::getFavoriteActivity($this, $request->get('therapist_id')),
        ];
    }
}
