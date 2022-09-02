<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
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
            'type' => $this->type,
            'file' => $this->file,
            'answers' => AnswerResource::collection($this->answers),
            'auto_translated' => $this->auto_translated,
            'parent_id' => $this->parent_id,
        ];
    }
}
