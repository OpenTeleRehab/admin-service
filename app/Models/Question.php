<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Question extends Model
{
    use HasTranslations, LogsActivity;

    const QUESTION_TYPE_CHECKBOX = 'checkbox';
    const QUESTION_TYPE_MULTIPLE = 'multiple';
    const QUESTION_TYPE_OPEN_NUMBER = 'open-number';
    const QUESTION_TYPE_OPEN_TEXT = 'open-text';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['title', 'questionnaire_id', 'type', 'file_id', 'order', 'auto_translated', 'parent_id', 'suggested_lang', 'mark_as_countable', 'mandatory'];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['title', 'auto_translated'];

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
            ->logExcept(['id', 'question_id', 'auto_translated', 'parent_id', 'suggested_lang', 'created_at', 'updated_at']);
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default ordering.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('order');
        });

        // Remove related objects.
        self::deleting(function ($question) {
            $question->answers()->each(function ($answer) {
                $answer->delete();
            });

            $question->file()->delete();
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id');
    }
}
