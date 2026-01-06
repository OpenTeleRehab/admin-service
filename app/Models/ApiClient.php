<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['name', 'api_key', 'secret_key', 'allow_ips', 'active'];

    protected $casts = [
        'allow_ips' => 'array',
        'active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['secret_key'];
}
