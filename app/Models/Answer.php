<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Answer extends Model
{
    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['description', 'question_id', 'auto_translated', 'parent_id', 'suggested_lang'];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['description', 'auto_translated'];
}
