<?php

namespace App\Helpers;

use App\Models\File;
use \Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Spatie\PdfToImage\Pdf;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

/**
 * @package App\Helpers
 */
class FileHelper
{
    const DEFAULT_EXT = ['image', 'audio', 'video', 'pdf'];

    /**
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $uploadPath
     * @param string $thumbnailPath
     *
     * @return mixed
     */
    public static function createFile(UploadedFile $file, $uploadPath, $thumbnailPath = null)
    {
        if (!self::validateMimeType($file)) {
            return false;
        };

        $path = $file->store($uploadPath);

        $record = File::create([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'content_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        if ($thumbnailPath) {
            $thumbnailFilePath = self::generateThumbnail($record, $thumbnailPath);

            if ($thumbnailFilePath) {
                $record->update(['thumbnail' => $thumbnailFilePath]);
            }
        }

        return $record;
    }

    /**
     * @param \App\Models\File $file
     *
     * @return \App\Models\File
     */
    public static function replicateFile(File $file)
    {
        $fileName = pathinfo($file->path, PATHINFO_FILENAME);
        $newFilePath = str_replace($fileName, Str::random(40), $file->path);
        Storage::copy($file->path, $newFilePath);

        $newFile = $file->replicate();
        $newFile->path = $newFilePath;
        $newFile->save();

        return $newFile;
    }

    /**
     * @param File $file
     * @param string $thumbnailFilePath
     *
     * @return string|null
     * @throws \Spatie\PdfToImage\Exceptions\PdfDoesNotExist
     */
    public static function generateThumbnail(File $file, $thumbnailFilePath)
    {
        $thumbnailImage = $file->id . '.jpg';
        $thumbnailFile = $thumbnailFilePath . '/' . $thumbnailImage;
        $destinationPath = storage_path('app/') . $file->path;
        $thumbnailPath = storage_path('app/') . $thumbnailFilePath;
        $thumbnailFileFullPath = storage_path('app/') . $thumbnailFile;

        if (!file_exists($destinationPath)){
            return null;
        }

        if (!file_exists($thumbnailPath)) {
            mkdir($thumbnailPath);
        }

        if (str_contains($file->content_type, 'image')) {
            Image::make($destinationPath)
                ->resize(320, 240)
                ->save($thumbnailFileFullPath);
            return $thumbnailFile;
        } elseif ($file->content_type === 'video/mp4') {
            FFMpeg::open($file->path)
            ->getFrameFromSeconds(1)
            ->export()
            ->save($thumbnailFile);
            
            return $thumbnailFile;
        } elseif ($file->content_type === 'application/pdf') {
            $pdf = new Pdf($destinationPath);
            $pdf->setResolution(48);
            $pdf->saveImage($thumbnailFileFullPath);
            return $thumbnailFile;
        }

        return null;
    }

    /**
     * @param \Illuminate\Http\UploadedFile $file
     *
     * @return boolean
     */
    private static function validateMimeType(UploadedFile $file)
    {
        foreach (self::DEFAULT_EXT as $value) {
            if (str_contains($file->getMimeType(), $value)) {
                return true;
            }
        }

        return false;
    }
}
