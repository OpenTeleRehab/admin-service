<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

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
     * Spatie\Activitylog config
     */
    protected static $logName = 'GlobalAssistiveTechnologyPatient';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;
}
