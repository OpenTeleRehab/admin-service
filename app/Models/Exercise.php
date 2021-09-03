<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations;

class Exercise extends Model
{
    use HasTranslations;
    use SoftDeletes;

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
    ];


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
    public $translatable = ['title'];

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
