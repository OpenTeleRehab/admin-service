<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ScreeningQuestionnaireQuestion extends Model
{
    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'question_text',
        'question_type',
        'mandatory',
        'order',
        'questionnaire_id',
        'section_id',
        'file_id',
        'auto_translated',
    ];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = [
        'question_text',
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
    }

    /**
     * Get the options for the question.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireQuestionOption::class, 'question_id');
    }

    /**
     * Get the logic for the question.
     */
    public function logics(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireQuestionLogic::class, 'question_id');
    }

    /**
     * Get the file that the question belongs to.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
