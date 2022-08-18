<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
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
            'fileName' =>  $this->filename,
            'fileType' =>  $this->content_type,
            'fileGroupType' => $this->getFileGroupType($this->content_type),
            'hasThumbnail' => $this->thumbnail !== null,
            'size' => $this->size,
        ];
    }

    /**
     * @param string $path
     * @return mixed
     */
    private function getFileGroupType($mineType)
    {
        $fileExtension = [
            // Text
            'text/plain' => 'common.type.text',

            // Images
            'image/png' => 'common.type.image',
            'image/jpeg' => 'common.type.image',
            'image/gif' => 'common.type.image',
            'image/bmp' => 'common.type.image',
            'image/tiff' => 'common.type.image',
            'image/svg+xml' => 'common.type.image',

            // Archives
            'application/zip' => 'common.type.archive',
            'application/gzip' => 'common.type.archive',
            'application/x-rar-compressed' => 'common.type.archive',
            'application/tar' => 'common.type.archive',
            'application/tar+gzip' => 'common.type.archive',

            // Audio/Video
            'audio/mpeg' => 'common.type.media',
            'video/quicktime' => 'common.type.media',
            'video/mp4' => 'common.type.media',

            // PDF
            'application/pdf' => 'common.type.pdf',

            // Document
            'application/msword' => 'common.type.document',
            'application/vnd.oasis.opendocument.text' => 'common.type.document',
            'application/rtf' => 'common.type.document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'common.type.document',

            // Spreadsheet
            'application/vnd.ms-excel' => 'common.type.spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'common.type.spreadsheet',
            'application/vnd.oasis.opendocument.spreadsheet' => 'common.type.spreadsheet',

            // Presentation
            'application/vnd.ms-powerpoint' => 'common.type.presentation',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'common.type.presentation'
        ];

        return isset($fileExtension[$mineType]) ? $fileExtension[$mineType] : 'common.type.unknown';
    }
}
