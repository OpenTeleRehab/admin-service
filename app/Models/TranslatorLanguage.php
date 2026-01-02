<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslatorLanguage extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['translator_id', 'language_id'];
}
