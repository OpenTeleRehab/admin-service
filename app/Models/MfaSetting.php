<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MfaSetting extends Model
{
    use HasFactory;

    const MFA_ENFORCE = 'force';
    const MFA_RECOMMEND = 'recommend';
    const MFA_DISABLE = 'skip';

    // Specific Keycloak keys
    const MFA_KEY_ENFORCEMENT = 'mfaEnforcement';
    const MFA_MAX_AGE = 'trustedDeviceMaxAge';
    const MFA_SKIP_MAX_AGE = 'skipMfaMaxAge';

    const ROLE_LEVEL = [
        'organization_admin' => 1,
        'country_admin' => 2,
        'regional_admin' => 3,
        'phc_service_admin' => 4,
        'clinic_admin' => 4,
        'therapist' => 5,
        'phc_worker' => 5,
    ];

    const ENFORCEMENT_LEVEL = [
        'force' => 1,
        'recommend' => 2,
        'skip' => 3,
    ];

    const TIME_UNIT_MULTIPLIER = [
        'seconds' => 1,
        'minutes' => 60,
        'hours' => 3600,
        'days' => 86400,
        'weeks' => 604800,
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'role',
        'organizations',
        'country_ids',
        'clinic_ids',
        'region_ids',
        'phc_service_ids',
        'mfa_enforcement',
        'mfa_expiration_duration',
        'skip_mfa_setup_duration',
        'mfa_expiration_unit',
        'skip_mfa_setup_unit',
    ];

    /**
     * Cast attributes column to array automatically.
     */
    protected $casts = [
        'organizations' => 'array',
        'country_ids' => 'array',
        'clinic_ids' => 'array',
        'region_ids' => 'array',
        'phc_service_ids' => 'array',
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
                $model->created_by_role = Auth::user()->type;
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }

            $hiOrganization = Organization::where('sub_domain_name', env('APP_NAME'))->first();

            if (Auth::user()->type !== User::ADMIN_GROUP_SUPER_ADMIN) {
                $model->organizations = [$hiOrganization->id];
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }

            $hiOrganization = Organization::where('sub_domain_name', env('APP_NAME'))->first();

            if (Auth::user()?->type !== User::ADMIN_GROUP_SUPER_ADMIN && $hiOrganization) {
                $model->organizations = [$hiOrganization->id];
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

    public function getMfaExpirationDurationInSecondsAttribute(): ?int
    {
        if (!$this->mfa_expiration_duration || !$this->mfa_expiration_unit) {
            return null;
        }

        return (int) (
            $this->mfa_expiration_duration *
            (self::TIME_UNIT_MULTIPLIER[$this->mfa_expiration_unit] ?? 1)
        );
    }

    public function getSkipMfaSetupDurationInSecondsAttribute(): ?int
    {
        if (!$this->skip_mfa_setup_duration || !$this->skip_mfa_setup_unit) {
            return null;
        }

        return (int) (
            $this->skip_mfa_setup_duration *
            (self::TIME_UNIT_MULTIPLIER[$this->skip_mfa_setup_unit] ?? 1)
        );
    }
}
