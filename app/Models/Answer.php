<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Answer extends Model
{
    use HasTranslations, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['description', 'question_id', 'auto_translated', 'parent_id', 'suggested_lang', 'value', 'threshold'];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['description', 'auto_translated'];

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
            ->logExcept(['id', 'answer_id', 'auto_translated', 'parent_id', 'suggested_lang', 'created_at', 'updated_at']);
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
        if ($this->question->questionnaire->therapist_id) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $this->question->questionnaire->therapist_id,
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
