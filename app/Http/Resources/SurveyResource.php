<?php

namespace App\Http\Resources;

use App\Models\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $language = Language::find($request->get('lang'));
        if ($language && $this->questionnaire) {
            $this->questionnaire->setLocale($language->code);
        }
        return [
            'id' => $this->id,
            'organization' => $this->organization,
            'role' => $this->role,
            'country' => $this->country,
            'region' => $this->region,
            'province' => $this->province,
            'phc_service' => $this->phc_service,
            'gender' => $this->gender,
            'location' => $this->location,
            'clinic' => $this->clinic,
            'date' => $this->date,
            'include_at_the_start' => $this->include_at_the_start,
            'include_at_the_end' => $this->include_at_the_end,
            'status' => $this->status,
            'questionnaire_id' => $this->questionnaire_id,
            'published_date' => $this->published_date,
            'questionnaire' => new QuestionnaireResource($this->questionnaire),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'frequency' => $this->frequency,
        ];
    }
}
