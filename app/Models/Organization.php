<?php

namespace App\Models;

use App\Helpers\OrganizationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

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
        'max_number_of_phc_worker',
    ];

    /**
     * Get the options for activity logging.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['id', 'created_at', 'updated_at', 'deleted_at']);
    }

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

        self::creating(function ($organization) {
            $organization->created_by = Auth::id();
        });

        self::created(function ($organization) {
            OrganizationHelper::sendEmailNotification($organization->admin_email, $organization->name, self::ONGOING_ORG_STATUS);
        });
    }
}
