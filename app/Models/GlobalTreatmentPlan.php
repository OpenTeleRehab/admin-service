<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class GlobalTreatmentPlan extends Model
{
    use Compoships, LogsActivity;

    const FINISHED_TREATMENT_PLAN = 'finished';
    const PLANNED_TREATMENT_PLAN = 'planned';
    const ONGOING_TREATMENT_PLAN = 'on_going';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'treatment_id',
        'name',
        'patient_id',
        'country_id',
        'start_date',
        'end_date',
        'status',
        'health_condition_id',
        'health_condition_group_id'
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
            ->logExcept(['id', 'created_at', 'updated_at', 'treatment_id']);
    }

    /**
     * Determine if the event should be logged.
     *
     * @param string $eventName
     * @return bool
     */
    public function shouldLogEvent(string $eventName): bool
    {
        return $eventName === 'deleted';
    }

    /**
     * The attributes that should be cast to native types.
     * This format will be used when the model is serialized to an array or JSON
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime:d/m/Y',
        'end_date' => 'datetime:d/m/Y',
    ];
}
