<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StaticPageResource extends JsonResource
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
            'content' => $this->content,
            'platform' => $this->platform,
            'url' => $this->url_path_segment,
            'file_id' => $this->file_id,
            'file' => new FileResource($this->file),
            'private' => $this->private,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'auto_translated' => $this->auto_translated,
        ];
    }
}
