<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    const SUPERSET_CONFIG = 'SUPERSET_CONFIG';

    protected $fillable = ['name', 'config'];

    protected $casts = [
        'config' => 'array',
    ];
}
