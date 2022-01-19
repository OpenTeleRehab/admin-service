<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Resources\Json\JsonResource;

class LogoAndColorSchemeResource extends JsonResource
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
            'web_logo_file_id' => $this->web_logo,
            'mobile_logo_file_id' => $this->mobile_logo,
            'favicon_logo_file_id' => $this->favicon,
            'web_logo' => new FileResource(File::find($this->web_logo)),
            'mobile_logo' => new FileResource(File::find($this->mobile_logo)),
            'favicon' => new FileResource(File::find($this->favicon)),
            'color' => $this->color
        ];
    }
}
