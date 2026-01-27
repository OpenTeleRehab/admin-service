<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Survey extends Model
{
    use LogsActivity;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'organization',
        'role',
        'country',
        'region',
        'province',
        'phc_service',
        'gender',
        'location',
        'clinic',
        'start_date',
        'end_date',
        'include_at_the_start',
        'include_at_the_end',
        'questionnaire_id',
        'status',
        'frequency',
        'published_date',
        'global'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'country' => 'array',
        'gender' => 'array',
        'organization' => 'array',
        'location' => 'array',
        'clinic' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'published_date' => 'date',
        'region' => 'array',
        'province' => 'array',
        'phc_service' => 'array',
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
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($survey) {
            $userId = auth()->id();
            $survey->author = $userId;
            $survey->last_modifier = $userId;
        });

        static::updating(function ($survey) {
            $userId = auth()->id();
            $survey->last_modifier = $userId;
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userSurveys(): HasMany
    {
        return $this->hasMany(UserSurvey::class, 'survey_id', 'id');
    }
}
