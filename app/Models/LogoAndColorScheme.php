<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogoAndColorScheme extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [ 'web_logo', 'mobile_logo', 'favicon', 'color' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
