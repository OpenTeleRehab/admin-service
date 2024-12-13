<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
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
            'description' => $this->description,
            'question_id' => $this->question_id,
            'parent_id' => $this->parent_id,
            'fallback' => [
                'description' => $this->getTranslation('description', config('app.fallback_locale'))
            ],
            'value' => $this->value,
            'threshold' => $this->threshold,
        ];
    }
}
