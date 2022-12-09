<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalAssistiveTechnologyPatient extends Model
{
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
}
