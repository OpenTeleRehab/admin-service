<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class AdditionalField extends Model
{
    use HasTranslations, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'field',
        'value',
        'exercise_id',
        'auto_translated',
        'parent_id',
        'suggested_lang',
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'AdditionalField';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['field', 'value', 'auto_translated'];
}
