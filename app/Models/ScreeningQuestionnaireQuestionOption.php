<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ScreeningQuestionnaireQuestionOption extends Model
{
    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'option_text',
        'option_point',
        'threshold',
        'min',
        'max',
        'question_id',
        'file_id',
        'auto_translated',
    ];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = [
        'option_text',
        'auto_translated',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Get the file that the question belongs to.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
