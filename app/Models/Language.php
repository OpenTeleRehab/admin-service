<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;

class Language extends Model
{
    use HasFactory, LogsActivity;

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
        'code', 'name', 'rtl', 'auto_translated',
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logAttributes = ['code', 'name', 'rtl', 'auto_translated'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderByRaw('FIELD(code, "' . config('app.fallback_locale') . '") DESC');
            $builder->orderBy('name');
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function countries()
    {
        return $this->hasMany(Country::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return bool
     */
    public function isUsed()
    {
        return $this->countries->count() > 0 || $this->users->count() > 0;
    }
}
