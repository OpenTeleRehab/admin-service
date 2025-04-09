<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use App\Models\Forwarder;
use App\Models\Questionnaire;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Question;

class QuestionnaireResultExport
{
    /**
     * @var string $exportDirectoryName
     */
    protected static $exportDirectoryName = 'exports/';

    /**
     * Exports data to an XLSX file.
     *
     * @param $payload.
     * @return string The path to the exported file.
     */
    public static function export($payload)
    {
        $translations = TranslationHelper::getTranslations($payload['lang'], 'therapist_portal');
        $basePath = 'app/' . self::$exportDirectoryName;
        $absolutePath = storage_path($basePath);

        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }

        $questionnaires = Questionnaire::where('is_survey', false)->get();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($questionnaires as $questionnaire) {
            $questions = [];
            $activities = [];

            if (!empty($questionnaire['questions'])) {
                $questions = $questionnaire['questions'];
                $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);
                $response = Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient-activities/list/by-filters', [
                    'activity_id' => $questionnaire['id'],
                    'type' => 'questionnaire',
                    'completed' => true,
                ]);
                $activities = $response->json('data');
                $hosts = config('settings.hosting_country');
                foreach ($hosts as $host) {
                    $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $host);
                    $response = Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/patient-activities/list/by-filters', [
                        'activity_id' => $questionnaire['id'],
                        'type' => 'questionnaire',
                        'completed' => true,
                    ]);
                    $hostActivities = $response->json('data');
                    $activities = array_merge($activities, $hostActivities);
                }
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(trim(mb_substr($questionnaire['title'] ?? '', 0, 29)) ?: 'Unknown');
            $sheet->mergeCells('A1:A2');
            $sheet->mergeCells('B1:B2');
            $sheet->mergeCells('C1:C2');
            $sheet->mergeCells('D1:D2');
            $sheet->mergeCells('E1:E2');

            $sheet->setCellValue('A1', $translations['report.questionnaire_result.patient_id']);
            $sheet->setCellValue('B1', $translations['report.questionnaire_result.diagnostic']);
            $sheet->setCellValue('C1', $translations['common.start_date']);
            $sheet->setCellValue('D1', $translations['common.end_date']);
            $sheet->setCellValue('E1', $translations['common.submitted_date']);
            $sheet->getColumnDimension('A')->setWidth(20);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->getColumnDimension('E')->setWidth(20);

            $colIndex = 6;
            foreach ($questions as $question) {
                // Each question spans 3 columns
                $endColIndex = $colIndex + 2;
                // Convert numeric column index to Excel column letters
                $startCol = Coordinate::stringFromColumnIndex($colIndex);
                $endCol = Coordinate::stringFromColumnIndex($endColIndex);

                $sheet->setCellValue($startCol . '1', $question['title']);
                $sheet->mergeCells($startCol . '1:' . $endCol . '1');

                // Answer row title
                $sheet->setCellValue($startCol . '2', $translations['report.questionnaire_result.answer']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . '2', $translations['report.questionnaire_result.value']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . '2', $translations['report.questionnaire_result.threshold']);
                $sheet->getColumnDimension($startCol)->setWidth(20);
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 1))->setWidth(20);
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 2))->setWidth(20);

                // Move to the next question (3 columns forward)
                $colIndex += 3;
            }

            if (isset($endCol)) {
                $sheet->getStyle('A1:' . $endCol . '2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:' . $endCol . '2')->getFont()->setBold(true);
                $sheet->getRowDimension('1')->setRowHeight(25);
                $sheet->getRowDimension('2')->setRowHeight(25);
                $sheet->getStyle('A1:' . $endCol . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1:' . $endCol . '2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }

            // Render data
            $row = 3;
            foreach ($activities as $activity) {
                $treatmentPlan = $activity['treatment_plan'];
                $patient = $treatmentPlan['user'];
                $questionnaireAnswers = $activity['answers'];
                $startDate = Carbon::createFromFormat('d/m/Y', $treatmentPlan['start_date'])->format('Y-m-d');
                $endDate = Carbon::createFromFormat('d/m/Y', $treatmentPlan['end_date'])->format('Y-m-d');
                $submittedDate = Carbon::createFromFormat('d/m/Y', $activity['submitted_date'])->format('Y-m-d');

                $sheet->setCellValue('A' . $row, $patient['identity'] ?? '');
                $sheet->setCellValue('B' . $row, $treatmentPlan['name'] ?? '');
                $sheet->setCellValue('C' . $row, $startDate);
                $sheet->setCellValue('D' . $row, $endDate);
                $sheet->setCellValue('E' . $row, $submittedDate);

                $colIndex = 6;
                foreach ($questions as $question) {
                    $patientAnswer = null;
                    foreach ($questionnaireAnswers as $questionnaireAnswer) {
                        if ($questionnaireAnswer['question_id'] === $question->id) {
                            $patientAnswer = $questionnaireAnswer['answer'];
                            break;
                        }
                    }

                    if ($patientAnswer) {
                        $answer = unserialize($patientAnswer);
                        $answerDescriptions = [];
                        $values = [];
                        $thresholds = [];

                        if ($question->type === Question::QUESTION_TYPE_CHECKBOX) {
                            $foundAnswers = $question->answers->filter(fn($questionAnswer) => in_array($questionAnswer->id, $answer))->all();
                            $answerDescriptions = array_column($foundAnswers, 'description');
                            $values = array_column($foundAnswers, 'value');
                            $thresholds = array_column($foundAnswers, 'threshold');
                        } else if ($question->type === Question::QUESTION_TYPE_MULTIPLE) {
                            $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->id === $answer);
                            $answerDescriptions[] = $foundAnswer->description;
                            $values[] = $foundAnswer->value ?? '';
                            $thresholds[] = $foundAnswer->threshold ?? '';
                        } else if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                            $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->question_id === $question->id);
                            $answerDescriptions[] = $answer;
                            $values[] = $foundAnswer ? $foundAnswer->value : '';
                            $thresholds[] = $foundAnswer ? $foundAnswer->threshold : '';
                        } else {
                            $answerDescriptions[] = $answer;
                        }

                        // For checkbox questions, we need to create a new row for each selected answer as it can be more than one answer
                        if ($question->type === Question::QUESTION_TYPE_CHECKBOX) {
                            foreach ($answerDescriptions as $index => $description) {
                                $startCol = Coordinate::stringFromColumnIndex($colIndex);
                                $sheet->setCellValue($startCol . $row, $description ?? '');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . $row, $values[$index] ?? '');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . $row, $thresholds[$index] ?? '');
                                $sheet->getRowDimension($row)->setRowHeight(20);
                                if (count($answerDescriptions) > 1) {
                                    $row++;
                                }
                            }
                        } else {
                            $startCol = Coordinate::stringFromColumnIndex($colIndex);
                            $sheet->setCellValue($startCol . $row, $answerDescriptions[0] ?? '');
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . $row, $values[0] ?? '');
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . $row, $thresholds[0] ?? '');
                            $sheet->getRowDimension($row)->setRowHeight(20);
                        }
                    }

                    // Move to the next set of columns (3 columns forward)
                    $colIndex += 3;
                }
                $sheet->getRowDimension($row)->setRowHeight(20);
                $row++;
            }

            if (isset($endCol)) {
                // Apply Borders and aling center to All Data Rows
                $sheet->getStyle('A3:' . $endCol . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A3:' . $endCol . ($row - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Questionnaire-Answers-' . date('Y-m-d_His') . '.xlsx';
        $filePath = $absolutePath . $fileName;

        $writer->save($filePath);
        return $basePath . $fileName;
    }
}
