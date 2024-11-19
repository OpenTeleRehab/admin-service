<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

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
        'deleted_at',
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'GlobalPatient';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function treatmentPlans()
    {
        return $this->hasMany(GlobalTreatmentPlan::class, 'patient_id', 'patient_id')->orWhere('country_id', $this->country_id);
    }
}
