<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

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
        'status',
        'created_by',
    ];

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
    }
}
