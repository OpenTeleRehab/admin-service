<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Spatie\Translatable\HasTranslations;

class InternationalClassificationDisease extends Model
{
    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['name'];

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
        $response = Http::get(env('PATIENT_SERVICE_URL') . '/api/treatment-plan/get-used-disease?disease_id=' . $this->id);

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
            $builder->orderBy('name->' . App::getLocale());
        });
    }
}
