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
            'file_id' => $this->file_id_no_fallback,
            'file' => $this->file_id_no_fallback ? new FileResource($this->file) : null,
            'categories' => $this->categories ? $this->categories->pluck('id') : [],
            'therapist_id' => $this->therapist_id,
            'is_favorite' => ContentHelper::getFavoriteActivity($this, $request->get('therapist_id')),
            'global' => $this->global,
            'auto_translated' => $this->auto_translated,
            'parent_id' => $this->parent_id,
            'children' => EducationMaterialResource::collection($this->children),
            'share_to_hi_library' => $this->share_to_hi_library,
            'suggested_lang' => $this->suggested_lang,
            'fallback' => [
                'title' => $this->getTranslation('title', config('app.fallback_locale')),
            ],
        ];
    }
}
