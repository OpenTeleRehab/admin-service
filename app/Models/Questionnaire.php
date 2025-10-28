<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Questionnaire extends Model
{
    use HasTranslations, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['title', 'description', 'is_used', 'therapist_id', 'global', 'questionnaire_id', 'auto_translated', 'parent_id', 'suggested_lang', 'share_to_hi_library', 'include_at_the_start', 'include_at_the_end', 'is_survey', 'share_with_phc_worker'];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['title', 'description', 'auto_translated'];

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
            ->logExcept(['id', 'global', 'questionnaire_id', 'auto_translated', 'parent_id', 'suggested_lang', 'is_survey', 'is_used', 'created_at', 'updated_at', 'deleted_at']);
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
        if ($this->therapist_id) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
                'id' => $this->therapist_id,
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
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        // Set default ordering.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('title->' . App::getLocale());
        });

        // Remove related objects.
        self::deleting(function ($questionnaire) {
            if ($questionnaire->forceDeleting) {
                $questionnaire->questions()->each(function ($question) {
                    $question->delete();
                });
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'questionnaire_categories', 'questionnaire_id', 'category_id');
    }
}
