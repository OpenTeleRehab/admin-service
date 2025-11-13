<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Province extends Model
{
    use SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'region_id',
        'name',
        'therapist_limit',
        'phc_worker_limit',
    ];

    /**
     * Get the country that owns the region.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function region()
    {
        return $this->belongsTo(Region::class);
    }

     /**
     * Get the phc services associated with this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function phcServices()
    {
        return $this->hasMany(PhcService::class);
    }

    /**
     * Get all clinics for the province.
     */
    public function clinics()
    {
        return $this->hasMany(Clinic::class);
    }

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

        self::creating(function ($province) {
            $user = Auth::user();

            if ($user->region_id) {
                $province->region_id = $user->region_id;
            }
        });
    }
}
