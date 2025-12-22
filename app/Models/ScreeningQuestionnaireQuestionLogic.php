<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ScreeningQuestionnaireQuestionLogic extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'question_id',
        'target_question_id',
        'target_option_id',
        'target_option_value',
        'condition_type',
        'condition_rule',
    ];

    /**
     * Get the options for the question.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ScreeningQuestionnaireQuestionOption::class, 'question_id');
    }

    /**
     * Get the file that the question belongs to.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
