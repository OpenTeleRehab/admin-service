<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Traits\LogsActivity;

class Profession extends Model
{
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'country_id'
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'Profession';
    protected static $logAttributes = ['name', 'country_id'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * @return array
     */
    public function isUsed()
    {
        $isUsed = false;
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/therapist/get-used-profession?profession_id=' . $this->id);

        if (!empty($response) && $response->successful()) {
            $isUsed = $response->json();
        }

        return $isUsed;
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order by name.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('name');
        });
    }
}
