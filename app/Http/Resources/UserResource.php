<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'type' => $this->type,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'country_name' => $this->country?->name,
            'enabled' => $this->enabled,
            'last_login' => $this->last_login,
            'gender' => $this->gender,
            'language_id' => $this->language_id,
            'therapist_limit' => $this->therapist_limit,
            'region_name' => $this->region?->name,
            'region_id' => $this->region_id,
            'phc_service' => new PhcServiceResource($this->phcService),
            'edit_languages' => $this->translatorLanguages->map(fn($lang) => [
                'id' => $lang->id,
                'name' => $lang->name,
                'code' => $lang->code,
            ]),
        ];
    }
}
