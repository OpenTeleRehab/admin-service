<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class PrivacyPolicy extends Model
{
    use HasTranslations, LogsActivity;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['version', 'content', 'status', 'published_date', 'auto_translated'];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'PrivacyPolicy';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'published_date' => 'datetime:d-m-Y',
    ];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['content', 'auto_translated'];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('published_date', 'desc');
        });
    }
}
