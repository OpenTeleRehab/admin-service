<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningQuestionnaireQuestionResource extends JsonResource
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
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'mandatory' => $this->mandatory,
            'order' => $this->order,
            'file' => $this->file,
            'options' => ScreeningQuestionnaireOptionResource::collection($this->options),
            'logics' => ScreeningQuestionnaireQuestionLogicResource::collection($this->logics),
        ];
    }
}
