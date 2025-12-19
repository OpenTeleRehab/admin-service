<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningQuestionnaireQuestionLogicResource extends JsonResource
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
            'question_id' => $this->question_id,
            'target_question_id' => $this->target_question_id,
            'target_option_id' => $this->target_option_id,
            'condition_type' => $this->condition_type,
            'condition_rule' => $this->condition_rule,
        ];
    }
}
