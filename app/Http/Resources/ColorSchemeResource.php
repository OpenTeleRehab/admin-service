<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Resources\Json\JsonResource;

class ColorSchemeResource extends JsonResource
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
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'primary_text_color' => $this->primary_text_color,
            'secondary_text_color' => $this->secondary_text_color,
        ];
    }
}
