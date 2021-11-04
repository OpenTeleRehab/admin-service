<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalTreatmentPlan extends Model
{

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
    ];

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
