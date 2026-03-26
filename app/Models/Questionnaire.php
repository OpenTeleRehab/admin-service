<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\ExtendActivity;

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
