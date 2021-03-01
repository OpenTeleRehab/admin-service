<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Spatie\Translatable\HasTranslations;

class StaticPage extends Model
{
    use HasTranslations;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['title', 'private', 'content', 'url_path_segment', 'file_id', 'platform'];

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['title', 'content'];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('title->' . App::getLocale());
        });
    }
}
