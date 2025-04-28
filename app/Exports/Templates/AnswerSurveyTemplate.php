<?php

namespace App\Exports\Templates;

use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Question;
use App\Models\Survey;
use App\Models\User;
use App\Models\UserSurvey;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AnswerSurveyTemplate
{
    /**
     * Renders data to the given sheet.
     *
     * @param Survey $survey
     * @param $sheet
     * @param $translations
     *
     * @return void
     */
    public function template(Survey $survey, $sheet, $translations)
    {
        $columns = self::headerColumns($survey);

        $headerColIndex = 1;
        $rowHeight = 20;
        $row = 3;
        $startRow = 3;

        // Render row header.
        foreach ($columns as $column) {
            $endColIndex = $headerColIndex;

            // Convert numeric column index to Excel column letters.
            $startCol = Coordinate::stringFromColumnIndex($headerColIndex);
            $endCol = Coordinate::stringFromColumnIndex($endColIndex);

            $sheet->mergeCells($startCol . '1:' . $startCol . '2');

            $sheet->setCellValue($startCol . '1', $column);
            $sheet->getColumnDimension($startCol)->setWidth(20);
            $headerColIndex += 1;
        }

        foreach ($survey->questionnaire->questions as $question) {
            $endColIndex = self::getDynamicEndColIndex($headerColIndex, $question->type);

            // Convert numeric column index to Excel column letters.
            $startCol = Coordinate::stringFromColumnIndex($headerColIndex);
            $endCol = Coordinate::stringFromColumnIndex($endColIndex);

            // Merge cells based on question type.
            if ($question->type !== Question::QUESTION_TYPE_OPEN_TEXT) {
                $sheet->mergeCells($startCol . '1:' . $endCol . '1');
            }

            $sheet->setCellValue($startCol . '1', $question->title);
            $sheet->setCellValue($startCol . '2', $translations['question.answer'] ?? 'Answer');

            if ($question->type !== Question::QUESTION_TYPE_OPEN_TEXT) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($headerColIndex + 1) . '2', $translations['question.answer_value'] ?? 'Value');
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($headerColIndex + 1))->setWidth(20);
            }

            if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($headerColIndex + 2) . '2', $translations['question.answer_threshold'] ?? 'Threshold');
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($headerColIndex + 2))->setWidth(20);
            }

            $sheet->getColumnDimension($startCol)->setWidth(20);
            $headerColIndex = self::getDynamicColIndex($headerColIndex, $question->type);
        }

        $sheet->getStyle('A1:' . $endCol . '1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:' . $endCol . '2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle('A1:' . $endCol . '1')->getFont()->setBold(true);
        $sheet->getStyle('A2:' . $endCol . '2')->getFont()->setBold(true);

        $sheet->getRowDimension('1')->setRowHeight(25);
        $sheet->getRowDimension('2')->setRowHeight(25);

        $sheet->getStyle('A1:' . $endCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:' . $endCol . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A1:' . $endCol . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:' . $endCol . '2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $surveyResults = $survey->userSurveys->where('status', UserSurvey::STATUS_COMPLETED);
        // Render row data.
        foreach ($surveyResults as $userSurvey) {
            $surveyInfo = self::getRowData($survey, $userSurvey, $translations);
            $maxAnswerRow = $row;
            $answerColIndex = count($columns) + 1;
            $answerStartRow = $row;
            foreach ($survey->questionnaire->questions as $index => $question) {
                switch ($question->type) {
                    case Question::QUESTION_TYPE_CHECKBOX:
                        // Increase row height based on answers.
                        $userAswer = collect($userSurvey->answer)->first(fn($surveyAnswer) => $surveyAnswer['question_id'] === $question->id);
                        $answer = self::getAnswerData($question, $userAswer['answer'] ?? null);
                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $answerRow = $answerStartRow;
                        foreach ($answer['description'] as $index => $description) {
                            $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                            $sheet->setCellValue($startCol . $answerRow , $description ?? '');
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($answerColIndex + 1) . $answerRow , $answer['value'][$index] ?? '');
                            $sheet->getRowDimension($answerRow)->setRowHeight(20);
                            $answerRow++;
                        }
                        // Set the max answer row for multiple answers.
                        $maxAnswerRow = max($maxAnswerRow, $answerRow - 1);
                        break;
                    case Question::QUESTION_TYPE_MULTIPLE:
                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $userAswer = collect($userSurvey->answer)->first(fn($surveyAnswer) => $surveyAnswer['question_id'] === $question->id);
                        $answer = self::getAnswerData($question, $userAswer['answer'] ?? null);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['description'][0] ?? '');

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex + 1);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['value'][0] ?? '');

                        break;
                    case Question::QUESTION_TYPE_OPEN_NUMBER:
                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $userAswer = collect($userSurvey->answer)->first(fn($surveyAnswer) => $surveyAnswer['question_id'] === $question->id);
                        $answer = self::getAnswerData($question, $userAswer['answer'] ?? null);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['description'][0] ?? '');

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex + 1);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['value'][0] ?? '');

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex + 2);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['threshold'][0] ?? '');

                        break;
                    default:
                        $answers = array_filter($userSurvey->answer, function($item) use ($question) {
                            return $item['question_id'] === $question->id;
                        });
                        $answer = reset($answers);

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['answer'] ?? '');
                }

                $answerColIndex = self::getDynamicColIndex($answerColIndex, $question->type);

                // Write the survey info to the sheet and merge cells of multiple answer rows.
                foreach ($surveyInfo as $key => $value) {
                    $startCol = Coordinate::stringFromColumnIndex($key + 1);

                    if ($answerStartRow !== $maxAnswerRow) {
                        $sheet->mergeCells($startCol . $answerStartRow . ':' . $startCol . $maxAnswerRow);
                    }

                    $sheet->setCellValue($startCol . $answerStartRow, $value);
                }

                $row = $maxAnswerRow + 1;
            }

            $sheet->getRowDimension($row)->setRowHeight($rowHeight);
        }
        // Apply borders and align center to all data rows.
        $sheet->getStyle('A2:' . $endCol . ($row === $startRow ? $row : $row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:' . $endCol . ($row === $startRow ? $row : $row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * @param Survey $survey
     * @return array
     */
    private static function headerColumns(Survey $survey)
    {
        $columns = [
            $translations['survey.created_by'] ?? 'Created By',
            $translations['common.status'] ?? 'Status',
            $translations['survey.organization'] ?? 'Organization',
            $translations['survey.user_role'] ?? 'User Role',
            $translations['survey.start_date'] ?? 'Start Date',
            $translations['survey.end_date'] ?? 'End Date',
            $translations['survey.frequency'] ?? 'Frequency',
            $translations['common.first_name'] ?? 'First Name',
            $translations['common.last_name'] ?? 'Last Name',
            $translations['questionnaire.submitted_date'] ?? 'Submitted Date',
            $translations['questionnaire.title'] ?? 'Title',
            $translations['questionnaire.description'] ?? 'Description',
        ];

        if ($survey->role === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $countryLabel = $translations['common.country'] ?? 'Country';

            array_splice($columns, 4, 0, array($countryLabel));
        }

        if ($survey->role === User::ADMIN_GROUP_CLINIC_ADMIN || $survey->role === User::GROUP_THERAPIST || $survey->role === User::GROUP_PATIENT) {
            $countryLabel = $translations['common.country'] ?? 'Country';
            $clinicLabel = $translations['common.clinic'] ?? 'Clinic';

            array_splice($columns, 4, 0, array($countryLabel, $clinicLabel));
        }

        if ($survey->role === User::GROUP_PATIENT) {
            $genderLabel = $translations['gender'] ?? 'Gender';
            $locationLabel = $translations['survey.location'] ?? 'Location';
            $includeStartLabel = $translations['questionnaire.include_at_the_start'] ?? 'Include at the start';
            $includeEndLabel = $translations['questionnaire.include_at_the_end'] ?? 'Include at the end';
            $patientIdLabel = $translations['report.patient_raw_data.patient_id'] ?? 'Patient ID';

            array_splice($columns, 6, 0, array($genderLabel, $locationLabel));
            array_splice($columns, 11, 0, array($includeStartLabel, $includeEndLabel, $patientIdLabel));

            array_splice($columns, 14, 2); // Remove first name and last name.
        }

        return $columns;
    }

    /**
     * @param Survey $survey
     * @param UserSurvey $userSurvey
     * @param array $translations
     *
     * @return array
     */
    private static function getRowData(Survey $survey, UserSurvey $userSurvey, array $translations)
    {
        if ($survey->role === User::GROUP_THERAPIST) {
            $surveyor = User::getTherapistById($userSurvey->user_id);
        } else if ($survey->role === User::GROUP_PATIENT) {
            $surveyor = User::getPatientById($userSurvey->user_id);
        } else {
            $surveyor = $userSurvey->user;
        }

        $data = [
            $survey->createdBy->first_name . ' ' . $survey->createdBy->last_name,
            $translations["survey.status.$survey->status"],
            Organization::findMany($survey->organization)->pluck('name')->implode(', '),
            $translations["common.$survey->role"],
            $survey->start_date ? Carbon::parse($survey->start_date)->format('d/M/Y') : '',
            $survey->end_date ? Carbon::parse($survey->end_date)->format('d/M/Y') : '',
            $translations["survey.frequency.$survey->frequency"],
            $surveyor->first_name ?? $translations['common.unknown'] ?? '',
            $surveyor->last_name ?? $translations['common.unknown'] ?? '',
            Carbon::parse($userSurvey->completed_at)->format('d/M/Y'),
            $survey->questionnaire->title,
            $survey->questionnaire->description,
        ];

        if ($survey->role === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $countryName = Country::findMany($survey->country)->pluck('name')->implode(', ');

            array_splice($data, 4, 0, array($countryName));
        }

        if ($survey->role === User::ADMIN_GROUP_CLINIC_ADMIN || $survey->role === User::GROUP_THERAPIST || $survey->role === User::GROUP_PATIENT) {
            $countryName = Country::findMany($survey->country)->pluck('name')->implode(', ');
            $clinicName = Clinic::findMany($survey->clinic)->pluck('name')->implode(', ');

            array_splice($data, 4, 0, array($countryName, $clinicName));
        }

        if ($survey->role == User::GROUP_PATIENT) {
            $genders = array_map(
                fn($gender) => $translations[$gender] ?? '',
                $survey->gender ?? []
            );

            $locations = array_map(
                fn($location) => $translations["common.$location"] ?? '',
                $survey->location ?? []
            );

            $genderLabel = implode(', ', array_filter($genders));
            $locationLabel = implode(', ', array_filter($locations));

            $includeStart = $survey->include_at_the_start ? $translations['common.yes'] : $translations['common.no'];
            $includeEnd = $survey->include_at_the_end ? $translations['common.yes'] : $translations['common.no'];

            array_splice($data, 6, 0, array($genderLabel, $locationLabel));
            array_splice($data, 11, 0, array($includeStart, $includeEnd, $surveyor->identity ?? ''));
            array_splice($data, 14, 2); // Remove first name and last name.
        }

        return $data;
    }

    /**
     * @param int $index
     * @param string $type
     *
     * @return int
     */
    private static function getDynamicColIndex(int $index, string $type)
    {
        switch ($type) {
            case Question::QUESTION_TYPE_MULTIPLE:
            case Question::QUESTION_TYPE_CHECKBOX:
                $index = $index + 2;
                break;
            case Question::QUESTION_TYPE_OPEN_NUMBER:
                $index = $index + 3;
                break;
            default:
                $index = $index + 1;
        }

        return $index;
    }

    /**
     * @param int $index
     * @param string $type
     *
     * @return int
     */
    private static function getDynamicEndColIndex(int $index, string $type)
    {
        switch ($type) {
            case Question::QUESTION_TYPE_MULTIPLE:
            case Question::QUESTION_TYPE_CHECKBOX:
                $index = $index + 1;
                break;
            case Question::QUESTION_TYPE_OPEN_NUMBER:
                $index = $index + 2;
                break;
        }

        return $index;
    }

    /**
     * @param Question $question
     * @param array $userAnswers
     * @return array
     */
    private function getAnswerData($question, $answer)
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
}
