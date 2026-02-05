<?php

namespace App\Models;

use App\Enums\ScreeningQuestionnaireQuestionType;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class ScreeningQuestionnaireAnswer extends Model
{
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'questionnaire_id',
        'user_id',
        'answers',
        'point',
    ];

    /**
     * Get the options for activity logging.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['id', 'answers', 'point', 'created_at', 'updated_at']);
    }

    /**
     * Modify the activity properties before it is saved.
     *
     * @param \Spatie\Activitylog\Models\Activity $activity
     * @return void
     */
    public function tapActivity(Activity $activity)
    {
        $user = Auth::user();
        if ($user->therapist_user_id) {
            $activity->causer_id = $user->therapist_user_id;
            $activity->country_id = $user->country_id;
            $activity->region_id = $user->region_id;
            $activity->province_id = $user->province_id;
            $activity->clinic_id = $user->clinic_id;
            $activity->phc_service_id = $user->phc_service_id;
            $activity->log_name = ExtendActivity::THERAPIST_SERVICE;
        }
    }

    /**
     * Get the questionnaire that owns the answer.
     */
    public function questionnaire()
    {
        return $this->belongsTo(ScreeningQuestionnaire::class);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $total_point = 0;

            $answers = json_decode($model->answers);

            foreach ($answers as $answer) {
                $question = ScreeningQuestionnaireQuestion::find($answer->question_id);

                if (in_array($question->question_type, [
                    ScreeningQuestionnaireQuestionType::CHECKBOX->value,
                    ScreeningQuestionnaireQuestionType::RADIO->value,
                ])) {
                    $total_point += $question->options()
                        ->whereIn('id', $answer->answer)
                        ->whereNotNull('option_point')
                        ->sum('option_point');
                }

                if ($question->question_type === ScreeningQuestionnaireQuestionType::RATING->value) {
                    $total_point += (int) $answer->answer;
                }

                if ($question->question_type === ScreeningQuestionnaireQuestionType::OPEN_NUMBER->value) {
                    $total_point += $question->options()->sum('option_point');
                }
            }

            $model->point = $total_point;
        });
    }
}
