<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalFieldResource extends JsonResource
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
            'field' =>  $this->field,
            'value' =>  $this->value,
            'auto_translated' => $this->auto_translated,
            'parent_id' => $this->parent_id,
            'fallback' => [
                'field' => $this->getTranslation('field', config('app.fallback_locale')),
                'value' => $this->getTranslation('value', config('app.fallback_locale'))
            ]
        ];
    }
}
