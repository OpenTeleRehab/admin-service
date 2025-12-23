<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ScreeningQuestionnaireSection extends Model
{
    use HasTranslations;

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
}
