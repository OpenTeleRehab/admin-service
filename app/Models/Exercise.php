<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class Exercise extends Model
{
    use HasTranslations, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'title',
        'sets',
        'reps',
        'include_feedback',
        'get_pain_level',
        'therapist_id',
        'global',
        'exercise_id',
        'auto_translated',
        'parent_id',
        'suggested_lang',
        'share_to_hi_library',
    ];

    / **
      * Spatie\Activitylog config
      */
    protected static $logName = 'Exercise';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'include_feedback' => 'boolean',
        'get_pain_level' => 'boolean',
    ];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['title', 'auto_translated'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function files()
    {
        return $this->belongsToMany(File::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function additionalFields()
    {
        return $this->hasMany(AdditionalField::class);
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
    protected static function boot()
    {
        parent::boot();

        // Set default order by title.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('title->' . App::getLocale());
        });

        // Remove related objects.
        self::deleting(function ($exercise) {
            if ($exercise->forceDeleting) {
                $exercise->files()->each(function ($file) {
                    $file->delete();
                });
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'exercise_categories', 'exercise_id', 'category_id');
    }
}
