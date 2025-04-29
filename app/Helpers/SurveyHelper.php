<?php

namespace App\Helpers;

use App\Models\Question;
use App\Models\UserSurvey;

class SurveyHelper
{
    /**
     * @param Question $question
     * @param array|string|integer $answer
     * @return array
     */
    public static function getAnswerData(Question $question, $answer)
    {
        $answerDescription = [];
        $value = [];
        $threshold = [];
        if ($answer) {
            if ($question->type === Question::QUESTION_TYPE_CHECKBOX) {
                $foundAnswers = $question->answers->filter(fn($questionAnswer) => in_array($questionAnswer->id, $answer))->all();
                $answerDescription = array_column($foundAnswers, 'description');
                $value = array_column($foundAnswers, 'value');
            } else if ($question->type === Question::QUESTION_TYPE_MULTIPLE) {
                $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->id === $answer);
                $answerDescription[] = $foundAnswer->description;
                $value[] = $foundAnswer->value ?? '';
            } else if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->question_id === $question->id);
                $answerDescription[] = $answer;
                $value[] = $foundAnswer ? $foundAnswer->value : '';
                $threshold[] = $foundAnswer ? $foundAnswer->threshold : '';
            } else {
                $answerDescription[] = $answer;
            }
        }
        return [
            'description' => $answerDescription,
            'value' => $value,
            'threshold' => $threshold,
        ];
    }

    /**
     * @param UserSurvey $userSurvey
     * @return integer
     */
    public static function getTotalScore(UserSurvey $userSurvey)
    {
        $total = 0;
        $survey = $userSurvey->survey;
        foreach ($survey->questionnaire->questions as $question) {
            $userAnswer = collect($userSurvey->answer)->first(fn($surveyAnswer) => $surveyAnswer['question_id'] === $question->id);
            $answer = self::getAnswerData($question, $userAnswer['answer'] ?? null);
            foreach ($answer['value'] as $value) {
                if (is_numeric($value)) {
                    $total += $value;
                }
            }
        }

        return $total;
    }
}
