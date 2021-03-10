<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireCategory extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'questionnaire_id',
        'category_id',
    ];
}
