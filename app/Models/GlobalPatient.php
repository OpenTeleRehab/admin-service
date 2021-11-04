<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalPatient extends Model
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
        'deleted_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function treatmentPlans()
    {
        return $this->hasMany(GlobalTreatmentPlan::class, 'patient_id', 'patient_id')->orWhere('country_id', $this->country_id);
    }
}
