<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MfaSetting extends Model
{
    use HasFactory;

    const MFA_ENFORCE = 'force';
    const MFA_RECOMMEND = 'recommend';
    const MFA_DISABLE = 'skip';

    // Specific attribute keys
    const MFA_KEY_ENFORCEMENT = 'mfa_enforcement';

    const ROLE_LEVEL = [
        'super_admin' => 1,
        'organization_admin' => 2,
        'country_admin' => 3,
        'clinic_admin' => 4,
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

    public function jobTrackers()
    {
        return $this->morphMany(JobTracker::class, 'trackable');
    }

}
