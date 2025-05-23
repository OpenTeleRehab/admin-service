<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSurvey extends Model
{
    const STATUS_COMPLETED = 'completed';
    const STATUS_SKIPPED = 'skipped';
    const SURVEY_PHASE_START = 'start';
    const SURVEY_PHASE_END = 'end';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'survey_id',
        'treatment_plan_id',
        'answer',
        'status',
        'completed_at',
        'skipped_at',
        'survey_phase',
        'score',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'answer' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'id');
    }
}
