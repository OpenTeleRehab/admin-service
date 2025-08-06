<?php

namespace App\Http\Resources;

use App\Models\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyListResource extends JsonResource
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
            'location' => $this->location,
            'clinic' => $this->clinic,
            'status' => $this->status,
            'published_date' => $this->published_date,
            'frequency' => $this->frequency
        ];
    }
}
