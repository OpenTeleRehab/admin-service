<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class GlobalAssistiveTechnologyPatient extends Model
{
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'gender',
        'date_of_birth',
        'identity',
        'country_id',
        'clinic_id',
        'enabled',
        'therapist_id',
        'assistive_technology_id',
        'provision_date',
        'deleted_at',
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
            ->logExcept(['id', 'created_at', 'updated_at', 'patient_id', 'therapist_id', 'gender', 'date_of_birth', 'country_id', 'clinic_id']);
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
}
