<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LanguageResource extends JsonResource
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
            'name' => $this->name,
            'code' => strtolower($this->code),
            'rtl' => $this->rtl,
            'fallback' => config('app.fallback_locale'),
            'is_used' => $this->isUsed(),
            'auto_translated' => $this->auto_translated,
        ];
    }
}
