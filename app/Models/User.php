<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    const ADMIN_GROUP_SUPER_ADMIN = 'super_admin';
    const ADMIN_GROUP_ORG_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const ADMIN_GROUP_REGIONAL_ADMIN = 'regional_admin';
    const ADMIN_GROUP_PHC_SERVICE_ADMIN = 'phc_service_admin';
    const GROUP_TRANSLATOR = 'translator';
    const GROUP_THERAPIST = 'therapist';
    const GROUP_PATIENT = 'patient';
    const GROUP_PHC_WORKER = 'phc_worker';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'type',
        'country_id',
        'clinic_id',
        'gender',
        'language_id',
        'enabled',
        'last_login',
        'region_id',
        'phc_service_id',
        'notifiable',
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
            ->logExcept(['id', 'password', 'last_login', 'created_at', 'updated_at', 'email_verified_at', 'remember_token']);
    }

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

        static::creating(function ($model) {
            $authUser = Auth::user();

            if (in_array($authUser?->type, [self::ADMIN_GROUP_COUNTRY_ADMIN, self::ADMIN_GROUP_REGIONAL_ADMIN])) {
                $model->country_id = $authUser->country_id;
            }

            if (in_array($authUser?->type, [self::ADMIN_GROUP_REGIONAL_ADMIN])) {
                $model->region_id = $authUser->region_id;
            }

            if (in_array($model?->type, [self::ADMIN_GROUP_CLINIC_ADMIN, self::ADMIN_GROUP_PHC_SERVICE_ADMIN])) {
                $model->notifiable = 1;
            }
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
     * Get the region that this user belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function phcService()
    {
        return $this->belongsTo(PhcService::class);
    }

    public function translatorLanguages()
    {
        return $this->belongsToMany(
            Language::class,
            'translator_languages',
            'translator_id',
            'language_id'
        );
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

    /**
     * @param int $therapistId
     * @return false|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public static function getTherapistById(int $therapistId)
    {
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);

        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-id', [
            'id' => $therapistId,
        ]);

        if ($response->successful()) {
            return json_decode($response->body());
        }

        return false;
    }

    /**
     * @param int $patientId
     * @return false|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public static function getPatientById(int $patientId)
    {
        $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        $response = Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient/id/' . $patientId);

        if ($response->successful()) {
            return json_decode($response->body());
        }

        return false;
    }

    /**
     * Route notifications for the mail channel.
     * @param Notification $notification
     *
     * @return string
     */
    public function routeNotificationForMail(Notification $notification): string
    {
        return $this->email;
    }
}
