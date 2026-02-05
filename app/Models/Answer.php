<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\ExtendActivity;

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
