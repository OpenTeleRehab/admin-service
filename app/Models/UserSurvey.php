<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class UserSurvey extends Model
{
    use LogsActivity;

    const STATUS_COMPLETED = 'completed';
    const STATUS_SKIPPED = 'skipped';
    const SURVEY_PHASE_START = 'start';
    const SURVEY_PHASE_END = 'end';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'survey_id',
        'treatment_plan_id',
        'answer',
        'status',
        'completed_at',
        'skipped_at',
        'survey_phase',
        'score',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'answer' => 'array',
    ];

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
            ->logExcept(['id', 'answer', 'score', 'created_at', 'updated_at']);
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
        if ($user->therapist_user_id || $user->patient_user_id) {
            $activity->causer_id = $user->therapist_user_id ? $user->therapist_user_id : $user->patient_user_id;
            $activity->country_id = $user->country_id;
            $activity->region_id = $user->region_id;
            $activity->province_id = $user->province_id;
            $activity->clinic_id = $user->clinic_id;
            $activity->phc_service_id = $user->phc_service_id;
            $activity->log_name = $user->therapist_user_id ? ExtendActivity::THERAPIST_SERVICE : ExtendActivity::PATIENT_SERVICE;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'id');
    }
}
