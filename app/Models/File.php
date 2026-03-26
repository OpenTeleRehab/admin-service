<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\ExtendActivity;

class File extends Model
{
    use LogsActivity;

    const EXERCISE_PATH = 'exercise';
    const EDUCATION_MATERIAL_PATH = 'education_material';
    const QUESTIONNAIRE_PATH = 'questionnaire';
    const SCREENING_QUESTIONNAIRE_PATH = 'screening_questionnaire';
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
            ->logExcept(['id', 'metadata', 'created_at', 'updated_at']);
    }

    /**
     * Modify the activity properties before it is saved.
     *
     * @param \Spatie\Activitylog\Models\Activity $activity
     * @return void
     */
    public function tapActivity(Activity $activity)
    {
        $user = Auth::user();
        if ($user->type === User::GROUP_THERAPIST) {
            $activity->causer_id = $user->therapist_user_id;
            $activity->country_id = $user->country_id;
            $activity->region_id = $user->region_id;
            $activity->province_id = $user->province_id;
            $activity->clinic_id = $user->clinic_id;
            $activity->log_name = ExtendActivity::THERAPIST_SERVICE;
        }
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
