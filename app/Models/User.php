<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    const ADMIN_GROUP_SUPER_ADMIN = 'super_admin';
    const ADMIN_GROUP_ORG_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const GROUP_TRANSLATOR = 'translator';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'type', 'country_id', 'clinic_id', 'gender', 'language_id', 'enabled', 'last_login'
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'User';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'password', 'last_login', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order by status (active/inactive), last name, and first name.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('enabled', 'desc');
            $builder->orderBy('last_name');
            $builder->orderBy('first_name');
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
