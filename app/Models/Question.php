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
        $therapist = null;
        if ($this->questionnaire->therapist_id) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $this->questionnaire->therapist_id,
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
