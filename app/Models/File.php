<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
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
        $therapist = null;
        $request = request();
        if ($request['therapist_id']) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $request['therapist_id'],
            ]);
            if (!empty($response) && $response->successful()) {
                $therapist = json_decode($response);
            } 
        }
        $activity->causer_id = $therapist ? $therapist->id : $user->id;
        $activity->full_name = $therapist ? $therapist->last_name . ' ' . $therapist->first_name : null; 
        $activity->clinic_id = $therapist ? $therapist->clinic_id : null;
        $activity->country_id = $therapist ? $therapist->country_id : null;
        $activity->group = $therapist ? 'therapist' : null;
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
