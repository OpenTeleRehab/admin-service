<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class Question extends Model
{
    use HasTranslations, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = ['title', 'questionnaire_id', 'type', 'file_id', 'order', 'auto_translated', 'parent_id', 'suggested_lang'];

    /**
     * Spatie\Activitylog config
     */
    protected static $logName = 'Question';
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;

    /**
     * The attributes that are translatable
     *
     * @var string[]
     */
    public $translatable = ['title', 'auto_translated'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default ordering.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('order');
        });

        // Remove related objects.
        self::deleting(function ($question) {
            $question->answers()->each(function ($answer) {
                $answer->delete();
            });

            $question->file()->delete();
        });
    }
}
