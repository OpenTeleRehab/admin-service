<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MfaSetting extends Model
{
    use HasFactory;

    const MFA_ENFORCE = 'force';
    const MFA_RECOMMEND = 'recommend';
    const MFA_DISABLE = 'skip';

    // Specific Keycloak keys
    const MFA_KEY_ENFORCEMENT = 'mfaPolicy';
    const MFA_MAX_AGE = 'trustedDeviceMaxAge';
    const MFA_SKIP_MAX_AGE = 'skipMfaMaxAge';

    const ROLE_LEVEL = [
        'organization_admin' => 1,
        'country_admin' => 2,
        'clinic_admin' => 3,
        'therapist' => 4,
    ];

    const ENFORCEMENT_LEVEL = [
        'force' => 1,
        'recommend' => 2,
        'skip' => 3,
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'role',
        'organizations',
        'country_ids',
        'clinic_ids',
        'attributes',
        'created_by',
        'updated_by',
    ];

    /**
     * Cast attributes column to array automatically.
     */
    protected $casts = [
        'organizations' => 'array',
        'country_ids' => 'array',
        'clinic_ids' => 'array',
        'attributes' => 'array',
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function jobTrackers()
    {
        return $this->morphMany(JobTracker::class, 'trackable');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
