<?php

namespace App\Http\Resources;

use App\Models\ScreeningQuestionnaireQuestion;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningQuestionnaireResource extends JsonResource
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
            'sections' => ScreeningQuestionnaireSectionResource::collection($this->sections),
            'total_question' => ScreeningQuestionnaireQuestion::where('questionnaire_id', $this->id)->count(),
            'published_date' => $this->published_date,
            'status' => $this->status,
            'auto_translated' => $this->auto_translated,
        ];
    }
}
