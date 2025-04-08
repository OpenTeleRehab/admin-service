<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use App\Models\Forwarder;
use App\Models\Questionnaire;
use App\Models\Country;
use App\Models\InternationalClassificationDisease;
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
            $sheet->mergeCells('F1:F2');
            $sheet->mergeCells('G1:G2');
            $sheet->mergeCells('H1:H2');
            $sheet->mergeCells('I1:I2');
            $sheet->mergeCells('J1:J2');
            $sheet->mergeCells('K1:K2');
            $sheet->mergeCells('L1:L2');
            $sheet->mergeCells('M1:M2');

            $sheet->setCellValue('A1', $translations['report.questionnaire_result.patient_id']);
            $sheet->setCellValue('B1', $translations['common.country']);
            $sheet->setCellValue('C1', $translations['common.gender']);
            $sheet->setCellValue('D1', $translations['common.date_of_birth']);
            $sheet->setCellValue('E1', $translations['common.age']);
            $sheet->setCellValue('F1', $translations['common.status']);
            $sheet->setCellValue('G1', $translations['common.location']);
            $sheet->setCellValue('H1', $translations['report.questionnaire_result.icd_classification']);
            $sheet->setCellValue('I1', $translations['report.questionnaire_result.diagnostic']);
            $sheet->setCellValue('J1', $translations['common.start_date']);
            $sheet->setCellValue('K1', $translations['common.end_date']);
            $sheet->setCellValue('L1', $translations['common.submitted_date']);
            $sheet->setCellValue('M1', $translations['report.questionnaire_result.questionnaire_name']);
            $sheet->getColumnDimension('A')->setWidth(20);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->getColumnDimension('E')->setWidth(20);
            $sheet->getColumnDimension('F')->setWidth(20);
            $sheet->getColumnDimension('G')->setWidth(20);
            $sheet->getColumnDimension('H')->setWidth(20);
            $sheet->getColumnDimension('I')->setWidth(20);
            $sheet->getColumnDimension('J')->setWidth(20);
            $sheet->getColumnDimension('K')->setWidth(20);
            $sheet->getColumnDimension('L')->setWidth(20);
            $sheet->getColumnDimension('M')->setWidth(20);

            $colIndex = 14;
            foreach ($questions as $question) {
                $endColIndex = $colIndex;
                // Each question spans 3 columns for open number and 2 for multiple and checkbox
                if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                    $endColIndex = $colIndex + 2;
                } else if ($question->type !== Question::QUESTION_TYPE_OPEN_TEXT) {
                    $endColIndex = $colIndex + 1;
                }
                
                // Convert numeric column index to Excel column letters
                $startCol = Coordinate::stringFromColumnIndex($colIndex);
                $endCol = Coordinate::stringFromColumnIndex($endColIndex);

                $sheet->setCellValue($startCol . '1', $question['title']);
                $sheet->mergeCells($startCol . '1:' . $endCol . '1');

                // Answer row title
                $sheet->setCellValue($startCol . '2', $translations['report.questionnaire_result.answer']);
                if ($question->type !== Question::QUESTION_TYPE_OPEN_TEXT) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . '2', $translations['report.questionnaire_result.value']);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 1))->setWidth(20);
                }
                
                if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . '2', $translations['report.questionnaire_result.threshold']);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 2))->setWidth(20);
                }
                $sheet->getColumnDimension($startCol)->setWidth(20);

                // Move to the next question
                if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                    $colIndex += 3;
                } else if ($question->type !== Question::QUESTION_TYPE_OPEN_TEXT) {
                    $colIndex += 2;
                }
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
                $country = Country::find($patient['country_id']);
                $age = Carbon::parse($patient['date_of_birth'])->age;
                $dob = Carbon::parse($patient['date_of_birth'])->toDateString();
                $status = $patient['enabled'] === 1 ? $translations['common.active'] : $translations['common.inactive'];
                $location = $translations['common.' . $patient['location']];
                $gender = $translations['common.' . $patient['gender']];
                $disease = InternationalClassificationDisease::find($treatmentPlan['disease_id']);
                $diseasName = $disease?->getTranslation('name', $payload['lang'] ?? 'en');

                $sheet->setCellValue('A' . $row, $patient['identity'] ?? '');
                $sheet->setCellValue('B' . $row, $treatmentPlan['name'] ?? '');
                $sheet->setCellValue('C' . $row, $startDate);
                $sheet->setCellValue('D' . $row, $endDate);
                $sheet->setCellValue('E' . $row, $submittedDate);
                $sheet->setCellValue('A' . $row, $patient['identity'] ?? '');
                $sheet->setCellValue('B' . $row, $country?->name ?? '');
                $sheet->setCellValue('C' . $row, $gender);
                $sheet->setCellValue('D' . $row, $dob);
                $sheet->setCellValue('E' . $row, $age);
                $sheet->setCellValue('F' . $row, $status);
                $sheet->setCellValue('G' . $row, $location);
                $sheet->setCellValue('H' . $row, $diseasName);
                $sheet->setCellValue('I' . $row, $treatmentPlan['name'] ?? '');
                $sheet->setCellValue('J' . $row, $startDate);
                $sheet->setCellValue('K' . $row, $endDate);
                $sheet->setCellValue('L' . $row, $submittedDate);
                $sheet->setCellValue('M' . $row, $questionnaire['title']);

                $colIndex = 14;
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
                        } else if ($question->type === Question::QUESTION_TYPE_MULTIPLE) {
                            $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->id === $answer);
                            $answerDescriptions[] = $foundAnswer->description;
                            $values[] = $foundAnswer->value ?? '';
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

                    // Move to the next set of columns
                    if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                        $colIndex += 3;
                    } else if ($question->type !== Question::QUESTION_TYPE_OPEN_TEXT) {
                        $colIndex += 2;
                    }
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
