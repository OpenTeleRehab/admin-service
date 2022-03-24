<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColorScheme extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [ 'primary_color', 'secondary_color' ];

}
