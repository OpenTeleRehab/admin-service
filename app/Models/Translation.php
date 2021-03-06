<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    const ADMIN_PORTAL = 'admin_portal';
    const THERAPIST_PORTAL = 'therapist_portal';
    const PATIENT_APP = 'patient_app';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'key', 'value', 'platform'
    ];
}
