<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScreeningQuestionnaireOptionResource extends JsonResource
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
            'option_text' => $this->option_text,
            'option_point' => $this->option_point,
            'threshold ' => $this->threshold,
            'min' => $this->min,
            'max' => $this->max,
            'file' => $this->file,
        ];
    }
}
