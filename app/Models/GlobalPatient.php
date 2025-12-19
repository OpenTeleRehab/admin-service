<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class GlobalPatient extends Model
{
    use Compoships, LogsActivity;

    const ADMIN_GROUP_ORG_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const FINISHED_TREATMENT_PLAN = 1;
    const PLANNED_TREATMENT_PLAN = 2;
    const ONGOING_TREATMENT_PLAN = 3;
    const SECONDARY_TERAPIST = 2;
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
        'phc_service_id',
        'enabled',
        'location',
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
            ->logExcept(['id', 'created_at', 'updated_at', 'patient_id', 'gender', 'date_of_birth', 'country_id', 'clinic_id', 'location', 'enabled', 'deleted_at']);
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function treatmentPlans()
    {
        return $this->hasMany(GlobalTreatmentPlan::class, ['patient_id', 'country_id'], ['patient_id', 'country_id']);
    }
}
