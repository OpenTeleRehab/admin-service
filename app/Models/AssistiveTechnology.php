<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class AssistiveTechnology extends Model
{
    use HasTranslations, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'file_id',
        'auto_translated'
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
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        self::deleting(function ($assistiveTechnology) {
            $assistiveTechnology->file()->delete();
        });
    }

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = [
        'name',
        'description',
        'auto_translated'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return array|false|mixed
     */
    public function isUsed()
    {
        $isUsed = false;
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE))->get(env('PATIENT_SERVICE_URL') . '/assistive-technologies/get-used-at?assistive_technology_id=' . $this->id);

        if (!empty($response) && $response->successful()) {
            $isUsed = $response->json();
        }

        return $isUsed;
    }
}
