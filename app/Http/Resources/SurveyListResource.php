<?php

namespace App\Http\Resources;

use App\Models\Language;
use App\Models\Region;
use App\Models\Province;
use App\Models\PhcService;
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
        $regionNames = Region::whereIn('id', $this->region ?? [])->pluck('name')->toArray();
        $provinceNames = Province::whereIn('id', $this->province ?? [])->pluck('name')->toArray();
        $phcServiceNames = PhcService::whereIn('id', $this->phc_service ?? [])->pluck('name')->toArray();
        return [
            'id' => $this->id,
            'organization' => $this->organization,
            'role' => $this->role,
            'country' => $this->country,
            'location' => $this->location,
            'clinic' => $this->clinic,
            'region' => $this->region,
            'province' => $this->province,
            'phc_service' => $this->phc_service,
            'status' => $this->status,
            'published_date' => $this->published_date,
            'frequency' => $this->frequency,
            'regionNames' => $regionNames,
            'provinceNames' => $provinceNames,
            'phcServiceNames' => $phcServiceNames,
        ];
    }
}
