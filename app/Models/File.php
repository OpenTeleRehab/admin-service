<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class File extends Model
{
    use LogsActivity;

    const EXERCISE_PATH = 'exercise';
    const EDUCATION_MATERIAL_PATH = 'education_material';
    const QUESTIONNAIRE_PATH = 'questionnaire';
    const EXERCISE_THUMBNAIL_PATH = self::EXERCISE_PATH . '/thumbnail';
    const EDUCATION_MATERIAL_THUMBNAIL_PATH = self::EDUCATION_MATERIAL_PATH . '/thumbnail';
    const STATIC_PAGE_PATH = 'static_page';
    const ASSISTIVE_TECHNOLOGY_PATH = 'assistive_technology';
    const FILE_PATH = 'file';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['filename', 'path', 'content_type', 'metadata', 'thumbnail', 'size'];

    /**
     * Get the options for activity logging.
     *
     * @return \Spatie\Activitylog\LogOptions
    */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Remove physical file.
        self::deleting(function ($file) {
            try {
                Storage::delete($file->path);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }
}
