<?php

namespace App\Models;

use App\Enums\ScreeningQuestionnaireQuestionType;
use Illuminate\Database\Eloquent\Model;

class ScreeningQuestionnaireAnswer extends Model
{
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
