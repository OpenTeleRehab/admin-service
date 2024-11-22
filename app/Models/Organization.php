<?php

namespace App\Models;

use App\Helpers\OrganizationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Organization extends Model
{
    use SoftDeletes, LogsActivity;

    const NON_HI_TYPE = 'non_hi';
    const HI_TYPE = 'hi';

    const ONGOING_ORG_STATUS = 'ongoing';
    const PENDING_ORG_STATUS = 'pending';
    const FAILED_ORG_STATUS = 'failed';
    const SUCCESS_ORG_STATUS = 'success';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'type',
        'admin_email',
        'sub_domain_name',
        'max_number_of_therapist',
        'max_ongoing_treatment_plan',
        'max_sms_per_week',
        'status',
        'created_by',
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('name');
        });

        self::created(function ($organization) {
            OrganizationHelper::sendEmailNotification($organization->admin_email, $organization->name, self::ONGOING_ORG_STATUS);
        });
    }
}
