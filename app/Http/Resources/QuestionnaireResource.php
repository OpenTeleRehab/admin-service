<?php

namespace App\Http\Resources;

use App\Helpers\FavoriteActivityHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionnaireResource extends JsonResource
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
            'is_used' => $this->is_used,
            'description' => $this->description,
            'questions' => QuestionResource::collection($this->questions),
            'categories' => $this->categories ? $this->categories->pluck('id') : [],
            'therapist_id' => $this->therapist_id,
            'is_favorite' => FavoriteActivityHelper::getFavoriteActivity($this, $request->get('therapist_id'))
        ];
    }
}
