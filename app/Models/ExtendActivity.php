<?php
namespace App\Models;
use Spatie\Activitylog\Models\Activity;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Clinic;

class ExtendActivity extends Activity
{
    public function user()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
