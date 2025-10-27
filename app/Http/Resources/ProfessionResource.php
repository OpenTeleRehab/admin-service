<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionResource extends JsonResource
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
            'identity' => str_pad($this->id, 4, '0', STR_PAD_LEFT),
            'name' => $this->name,
            'country_id' => $this->country_id,
            'type' => $this->type,
        ];
    }
}
