<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class FavoriteActivitiesTherapist extends Model
{
    use LogsActivity;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'activity_id',
        'therapist_id',
        'type',
        'is_favorite'
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'FavoriteActivitiesTherapist';
    protected static $logAttributes = ['activity_id', 'therapist_id', 'type', 'is_favorite'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;
}
