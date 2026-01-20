<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Translation extends Model
{
    use LogsActivity;

    const ADMIN_PORTAL = 'admin_portal';
    const THERAPIST_PORTAL = 'therapist_portal';
    const PATIENT_APP = 'patient_app';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'key', 'value', 'platform'
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
            ->logExcept(['id']);
    }

    /**
     * Determine if the event should be logged.
     *
     * @param string $eventName
     * @return bool
     */
    public function shouldLogEvent(string $eventName): bool
    {
        return $eventName === 'updated';
    }

    /**
     * Get the localizations for the translation.
     */
    public  function localizations()
    {
        return $this->hasMany(Localization::class);
    }
}
