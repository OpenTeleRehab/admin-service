<?php

namespace App\Exports\Templates;

use App\Helpers\SurveyHelper;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Province;
use App\Models\Question;
use App\Models\Region;
use App\Models\Survey;
use App\Models\User;
use App\Models\UserSurvey;
use App\Models\PhcService;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
                        $answer = SurveyHelper::getAnswerData($question, $userAswer['answer'] ?? null);
                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $answerRow = $answerStartRow;
                        foreach ($answer['description'] as $index => $description) {
                            $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                            $sheet->setCellValue($startCol . $answerRow, $description ?? '');
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($answerColIndex + 1) . $answerRow, $answer['value'][$index] ?? '');
                            $sheet->getRowDimension($answerRow)->setRowHeight(20);
                            $answerRow++;
                        }
                        // Set the max answer row for multiple answers.
                        $maxAnswerRow = max($maxAnswerRow, $answerRow - 1);
                        break;
                    case Question::QUESTION_TYPE_MULTIPLE:
                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $userAswer = collect($userSurvey->answer)->first(fn($surveyAnswer) => $surveyAnswer['question_id'] === $question->id);
                        $answer = SurveyHelper::getAnswerData($question, $userAswer['answer'] ?? null);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['description'][0] ?? '');

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex + 1);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['value'][0] ?? '');

                        break;
                    case Question::QUESTION_TYPE_OPEN_NUMBER:
                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex);
                        $userAswer = collect($userSurvey->answer)->first(fn($surveyAnswer) => $surveyAnswer['question_id'] === $question->id);
                        $answer = SurveyHelper::getAnswerData($question, $userAswer['answer'] ?? null);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['description'][0] ?? '');

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex + 1);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['value'][0] ?? '');

                        $startCol = Coordinate::stringFromColumnIndex($answerColIndex + 2);
                        $sheet->setCellValue($startCol . $answerStartRow, $answer['threshold'][0] ?? '');

                        break;
                    default:
                        $answers = array_filter($userSurvey->answer, function ($item) use ($question) {
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
        $columns = [];
        $baseColumns = [
            $translations['survey.created_by'] ?? 'Created By',
            $translations['common.status'] ?? 'Status',
            $translations['survey.organization'] ?? 'Organization',
            $translations['survey.user_role'] ?? 'User Role',
            $translations['survey.start_date'] ?? 'Start Date',
            $translations['survey.end_date'] ?? 'End Date',
            $translations['survey.frequency'] ?? 'Frequency',
            $translations['questionnaire.submitted_date'] ?? 'Submitted Date',
            $translations['questionnaire.title'] ?? 'Title',
            $translations['questionnaire.description'] ?? 'Description',
        ];
        $labelMap = [
            'country' => $translations['common.country'] ?? 'Country',
            'region' => $translations['common.region'] ?? 'Region',
            'province' => $translations['common.province'] ?? 'Province',
            'clinic' => $translations['common.clinic'] ?? 'Rehab Service',
            'phc_service' => $translations['common.phc_service'] ?? 'PHC Service',
        ];
        $roleColumns = [
            User::ADMIN_GROUP_COUNTRY_ADMIN => ['country'],
            User::ADMIN_GROUP_REGIONAL_ADMIN => ['country', 'region'],
            User::ADMIN_GROUP_CLINIC_ADMIN => ['country', 'region', 'province', 'clinic'],
            User::ADMIN_GROUP_PHC_SERVICE_ADMIN => ['country', 'region', 'province', 'phc_service'],
            User::GROUP_THERAPIST => ['country', 'region', 'province', 'clinic'],
            User::GROUP_PHC_WORKER => ['country', 'region', 'province', 'phc_service'],
            User::GROUP_PATIENT => ['country', 'region', 'province'],
        ];

        if ($survey->role === User::GROUP_PATIENT && !empty($survey->clinic)) {
            $roleColumns[User::GROUP_PATIENT][] = 'clinic';
        } elseif ($survey->role === User::GROUP_PATIENT && !empty($survey->phc_service)) {
            $roleColumns[User::GROUP_PATIENT][] = 'phc_service';
        }

        foreach ($roleColumns[$survey->role] ?? [] as $key) {
            $columns[] = $labelMap[$key];
        }

        if ($survey->role === User::GROUP_PATIENT) {
            $columns[] = $translations['gender'] ?? 'Gender';
            $columns[] = $translations['survey.location'] ?? 'Location';

            $columns[] = $translations['questionnaire.include_at_the_start'] ?? 'Include at the start';
            $columns[] = $translations['questionnaire.include_at_the_end'] ?? 'Include at the end';
            $columns[] = $translations['report.patient_raw_data.patient_id'] ?? 'Patient ID';
        }

        array_splice($baseColumns, 4, 0, $columns);

        return $baseColumns;
    }

    /**
     * @param Survey $survey
     * @param UserSurvey $userSurvey
     * @param array $translations
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    private static function getRowData(Survey $survey, UserSurvey $userSurvey, array $translations)
    {
        $data = [];
        $baseData = [
            $survey->createdBy->first_name . ' ' . $survey->createdBy->last_name,
            $translations["survey.status.$survey->status"] ?? 'N/A',
            Organization::findMany($survey->organization)->pluck('name')->implode(', '),
            $translations["common.$survey->role"] ?? 'N/A',
            $survey->start_date ? Carbon::parse($survey->start_date)->format('d/M/Y') : '',
            $survey->end_date ? Carbon::parse($survey->end_date)->format('d/M/Y') : '',
            $translations["survey.frequency.$survey->frequency"] ?? 'N/A',
            Carbon::parse($userSurvey->completed_at)->format('d/M/Y'),
            $survey->questionnaire->title,
            $survey->questionnaire->description,
        ];
        $resolvers = [
            'country' => fn() => Country::findMany((array)$survey->country)->pluck('name')->implode(', '),
            'region' => fn() => Region::findMany((array)$survey->region)->pluck('name')->implode(', '),
            'province' => fn() => Province::findMany((array)$survey->province)->pluck('name')->implode(', '),
            'clinic' => fn() => Clinic::findMany((array)$survey->clinic)->pluck('name')->implode(', '),
            'phc_service' => fn() => PhcService::findMany((array)$survey->phc_service)->pluck('name')->implode(', '),
        ];
        $roleFields = [
            User::ADMIN_GROUP_COUNTRY_ADMIN => ['country'],
            User::ADMIN_GROUP_REGIONAL_ADMIN => ['country', 'region'],
            User::ADMIN_GROUP_CLINIC_ADMIN => ['country', 'region', 'province', 'clinic'],
            User::ADMIN_GROUP_PHC_SERVICE_ADMIN => ['country', 'region', 'province', 'phc_service'],
            User::GROUP_THERAPIST => ['country', 'region', 'province', 'clinic'],
            User::GROUP_PHC_WORKER => ['country', 'region', 'province', 'phc_service'],
            User::GROUP_PATIENT => ['country', 'region', 'province'],
        ];

        if ($survey->role === User::GROUP_PATIENT && !empty($survey->clinic)) {
            $roleFields[User::GROUP_PATIENT][] = 'clinic';
        } elseif ($survey->role === User::GROUP_PATIENT && !empty($survey->phc_service)) {
            $roleFields[User::GROUP_PATIENT][] = 'phc_service';
        }

        foreach ($roleFields[$survey->role] ?? [] as $field) {
            $data[] = $resolvers[$field]();
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

            $data[] = implode(', ', array_filter($genders));
            $data[] = implode(', ', array_filter($locations));

            $data[] = $survey->include_at_the_start ? $translations['common.yes'] : $translations['common.no'];
            $data[] = $survey->include_at_the_end ? $translations['common.yes'] : $translations['common.no'];

            if ($survey->role === User::GROUP_THERAPIST || $survey->role === User::GROUP_PHC_WORKER) {
                $surveyor = User::getTherapistById($userSurvey->user_id);
            } else if ($survey->role === User::GROUP_PATIENT) {
                $surveyor = User::getPatientById($userSurvey->user_id);
            } else {
                $surveyor = $userSurvey->user;
            }

            $data[] = $surveyor->identity ?? '';
        }

        array_splice($baseData, 4, 0, $data);

        return $baseData;
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
}
