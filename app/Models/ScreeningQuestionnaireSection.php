<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ScreeningQuestionnaireSection extends Model
{
    use HasTranslations, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'title',
        'description',
        'order',
        'questionnaire_id',
        'auto_translated',
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
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = [
        'title',
        'description',
        'auto_translated',
    ];

    /**
     * Get the questions for the section.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireQuestion::class, 'section_id');
    }

    /**
     * Get the actions for the section.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireAction::class, 'section_id');
    }
}
