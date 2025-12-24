<?php

namespace App\Http\Resources;

use App\Models\ScreeningQuestionnaireAnswer;
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
        $userId = $request->get('user_id');

        $totalQuestion = ScreeningQuestionnaireQuestion::where('questionnaire_id', $this->id)
            ->count();

        $totalInterviewHistory = ScreeningQuestionnaireAnswer::where('questionnaire_id', $this->id)
            ->where('user_id', $userId)
            ->count();

        $isUsed = ScreeningQuestionnaireAnswer::where('questionnaire_id', $this->id)->exists();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'sections' => ScreeningQuestionnaireSectionResource::collection($this->sections),
            'total_question' => $totalQuestion,
            'total_interview_history' => $totalInterviewHistory,
            'published_date' => $this->published_date,
            'status' => $this->status,
            'auto_translated' => $this->auto_translated,
            'isUsed' => $isUsed,
        ];
    }
}

