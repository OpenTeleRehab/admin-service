<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Region extends Model
{
    use SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id',
        'name',
        'therapist_limit',
        'phc_worker_limit',
    ];

    /**
     * Get the country that owns the region.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get all clinices that belongs to this region
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clinics()
    {
        return $this->hasMany(Clinic::class);
    }

    /**
     * Get the provinces associated with this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function provinces()
    {
        return $this->hasMany(Province::class);
    }

    /**
     * Get the phc services associated with this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function phcServices()
    {
        return $this->hasManyThrough(
            PhcService::class,
            Province::class,
            'region_id',
            'province_id',
            'id',
            'id'
        );
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

        self::creating(function ($region) {
            $user = Auth::user();

            if ($user->country_id) {
                $region->country_id = $user->country_id;
            }
        });
    }
}
