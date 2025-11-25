<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HealthConditionResource extends JsonResource
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
            'title' => $this->title,
            'parent_id' => $this->parent_id,
            'is_used' => $this->isUsed(),
            'parent' => new HealthConditionGroupResource($this->whenLoaded('parent')),
            'auto_translated' => $this->auto_translated,
        ];
    }
}
