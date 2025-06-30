<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class HealthConditionGroup extends Model
{
    use HasTranslations, HasFactory, LogsActivity;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['title', 'auto_translated'];

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

        // Remove children objects.
        self::deleting(function ($healthConditionGroup) {
            $healthConditionGroup->healthConditions()->each(function ($subCat) {
                $subCat->delete();
            });
        });
    }

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
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['title', 'auto_translated'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function healthConditions()
    {
        return $this->hasMany(HealthCondition::class, 'parent_id', 'id');
    }
}
