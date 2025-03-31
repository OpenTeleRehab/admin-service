<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;

class GlobalPatient extends Model
{
    use LogsActivity;

    const ADMIN_GROUP_ORG_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const FINISHED_TREATMENT_PLAN = 1;
    const PLANNED_TREATMENT_PLAN = 2;
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
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * Modify the activity properties before it is saved.
     *
     * @param \Spatie\Activitylog\Models\Activity $activity
     * @return void
     */
    public function tapActivity(Activity $activity, string $eventName)
    {
        if ($eventName === 'created') {
            $activity->properties = [
                'attributes' => ['identity' => $this->identity],
            ];
        } else if ($eventName === 'updated') {
            $activity->properties = [
                'old' => ['identity' => $this->identity],
                'attributes' => ['identity' => $this->identity],
            ];
        } else {
            $activity->properties = [
                'old' => ['identity' => $this->identity],
            ];
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function treatmentPlans()
    {
        return $this->hasMany(GlobalTreatmentPlan::class, 'patient_id', 'patient_id')->orWhere('country_id', $this->country_id);
    }
}
