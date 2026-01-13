<?php

namespace App\Models;

use App\Enums\ScreeningQuestionnaireStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class ScreeningQuestionnaire extends Model
{
    use HasTranslations, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'title',
        'description',
        'published_date',
        'status',
        'auto_translated',
    ];

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
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $model['status'] = ScreeningQuestionnaireStatus::DRAFT;
        });
    }

    /**
     * Get the sections for the questionnaire.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireSection::class, 'questionnaire_id');
    }

    /**
     * Get the answers that belong to the questionnaire.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireAnswer::class, 'questionnaire_id');
    }
}
