<?php

namespace App\Exports;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Models\Forwarder;
use App\Models\Clinic;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Helpers\TranslationHelper;
use App\Models\AssistiveTechnology;
use App\Models\Country;
use App\Models\InternationalClassificationDisease;
use App\Models\Language;
use App\Models\Questionnaire;
use App\Models\Question;
use App\Models\UserSurvey;
use App\Exports\Templates\PatientTreatmentTemplate;
use App\Exports\Templates\PatientAssistiveTemplate;
use App\Exports\Templates\PatientSurveyTemplate;
use App\Exports\Templates\PatientTreatmentPlanSurveyTemplate;
use App\Models\Survey;
use App\Models\User;

class PatientRawDataExport
{
    /**
     * @var string $exportDirectoryName
     */
    protected static $exportDirectoryName = 'exports/';

    /**
     * Exports data to an XLSX file.
     *
     * @param \Illuminate\Http\Request $request.
     * @return string The path to the exported file.
     */
    public static function export(Request $request)
    {
        $translations = TranslationHelper::getTranslations($request->get('lang'));
        $language = Language::find($request->get('lang'));
        $basePath = 'app/' . self::$exportDirectoryName;
        $absolutePath = storage_path($basePath);
        $hosts = config('settings.hosting_country');

        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }

        $patients = [];
        $therapists = [];

        // Get patient data from other host
        foreach ($hosts as $host) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $host);
            $response = json_decode(Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/patient/list/get-raw-data', $request->all()));
            $patients = array_merge($patients, $response->data);
        }

        // Get patient data from global host
        $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);
        $response = Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient/list/get-raw-data', $request->all());
        if (!empty($response) && $response->successful()) {
            $response = json_decode($response);
            $patients = array_merge($patients, $response->data);
        }
        // Get therapist data
        $uniqueTherapistIds = array_values(array_unique(array_merge(
            array_column($patients, 'therapist_id'), 
            ...array_map(fn($patient) => $patient->secondary_therapists, $patients)
        )));
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/therapist/by-ids', [
            'ids' => json_encode($uniqueTherapistIds),
        ]);

        if (!empty($response) && $response->successful()) {
            $therapists = json_decode($response);
        }
        
        $patientTreatmentPlanData = [];
        $patientTreatmentPlanSurveyData = [];
        $patientAssistiveData = [];
        $patientSurveyData = [];

        foreach ($patients as $patient) {
            $clinic = Clinic::find($patient->clinic_id);
            $country = Country::find($patient->country_id);
            $age = Carbon::parse($patient->date_of_birth)->age;
            $dob = Carbon::parse($patient->date_of_birth)->toDateString();
            $status = $patient->enabled === 1 ? $translations['common.active'] : $translations['common.inactive'];
            $location = $translations['common.' . $patient->location];
            $gender = $translations[$patient->gender];
            $therapist = current(array_filter($therapists->data, fn($therapist) => $therapist->id === $patient->therapist_id));
            $secondaryTherapists = array_filter($therapists->data, fn($therapist) => in_array($therapist->id, $patient->secondary_therapists));
            $therapistName = $therapist?->last_name . ' ' . $therapist?->first_name;
            $secondaryTherapistsNames = implode(', ', array_map(fn($therapist) => $therapist->last_name . ' ' . $therapist->first_name, $secondaryTherapists));
            $patientData = [
                $clinic?->name,
                $patient->identity,
                $country?->name,
                $gender,
                $dob,
                $age,
                $status,
                $location,
                $therapistName,
                $secondaryTherapistsNames,
                $patient->call,
            ];

            $patientAssistiveData = array_merge($patientAssistiveData, self::getPatientAssistiveTechnologyData($patient->assistiveTechnologies, $patientData));
            $patientSurveyData = array_merge($patientSurveyData, self::getPatientSurveyData($patient->id, $patientData));

            if (count((array) $patient->treatmentPlans) > 0) {
                foreach ($patient->treatmentPlans as $index => $treatmentPlan) {
                    $disease = InternationalClassificationDisease::find($treatmentPlan->disease_id);
                    $diseasName = $disease?->getTranslation('name', $language?->code ?? 'en');
                    $startDate = Carbon::createFromFormat('d/m/Y', $treatmentPlan->start_date)->format('Y-m-d');
                    $endDate = Carbon::createFromFormat('d/m/Y', $treatmentPlan->end_date)->format('Y-m-d');
                    $treatmentStatus = self::getTreatmentPlanStatus($startDate, $endDate, $translations);
                    $numberOfExercise = array_sum(array_map(fn($item) => (int) $item->number_of_exercise ?? 0, $treatmentPlan->activities));
                    $numberOfcompletedExercise = array_sum(array_map(fn($item) => (int) $item->number_of_completed_exercise ?? 0, $treatmentPlan->activities));
                    $totalPainLevel = array_sum(array_map(fn($item) => (int) ($item->total_pain_level ?? 0), $treatmentPlan->activities));
                    $numberOfSubmittedPainLevel = array_sum(array_map(fn($item) => (int) ($item->number_of_submitted_pain_level ?? 0), $treatmentPlan->activities));
                    $numberOfSubmittedDailyGoal = array_sum(array_map(fn($item) => (int) ($item->number_of_submitted_goal ?? 0), $treatmentPlan->dailyGoals));
                    $numberOfSubmittedWeeklyGoal = array_sum(array_map(fn($item) => (int) ($item->number_of_submitted_goal ?? 0), $treatmentPlan->weeklyGoals));
                    $totalSatisfactionDailyGoal = array_sum(array_map(fn($item) => (int) ($item->satisfaction ?? 0), $treatmentPlan->dailyGoals));
                    $totalSatisfactionWeeklyGoal = array_sum(array_map(fn($item) => (int) ($item->satisfaction ?? 0), $treatmentPlan->weeklyGoals));
                    $averageExercise = $numberOfExercise > 0 ? round($numberOfcompletedExercise / $numberOfExercise, 2) : 0;
                    $averagePainLevel = $numberOfSubmittedPainLevel > 0 ? round($totalPainLevel / $numberOfSubmittedPainLevel, 2) : 0;
                    $averageDailyGoal = $numberOfSubmittedDailyGoal > 0 ? round($totalSatisfactionDailyGoal / $numberOfSubmittedDailyGoal, 2) : 0;
                    $averageWeeklyGoal = $numberOfSubmittedWeeklyGoal > 0 ? round($totalSatisfactionWeeklyGoal / $numberOfSubmittedWeeklyGoal, 2) : 0;
                    
                    // Find questionnaire that include at start and end 
                    $treatmentPlanQuestionnaireIds = array_column($treatmentPlan->questionnaires, 'id');
                    $questionnaireAtStart = Questionnaire::where('include_at_the_start', 1)->whereIn('id', $treatmentPlanQuestionnaireIds)->first();
                    $questionnaireAtEnd = Questionnaire::where('include_at_the_end', 1)->whereIn('id', $treatmentPlanQuestionnaireIds)->first();
                    
                    $questionnResultAtStart = 0;
                    $questionnResultAtEnd = 0;

                    // Get answer result of questionnaire at start and end of treatment plan
                    if (!empty($questionnaireAtStart)) {
                        $treatmentQuestionAtStart = current(array_filter($treatmentPlan->questionnaires, fn($treatmentQuestionnaire) => $treatmentQuestionnaire->id === $questionnaireAtStart->id));
                        $questionnResultAtStart = self::getQuestionnaireResult($questionnaireAtStart, $treatmentQuestionAtStart->answer);
                    }
                    if (!empty($questionnaireAtEnd)) {
                        $treatmentQuestionAtEnd = current(array_filter($treatmentPlan->questionnaires, fn($treatmentQuestionnaire) => $treatmentQuestionnaire->id === $questionnaireAtEnd->id));
                        $questionnResultAtEnd = self::getQuestionnaireResult($questionnaireAtEnd, $treatmentQuestionAtEnd->answer);
                    }

                    $treatmentPlanData = array_merge($patientData, [
                        $treatmentPlan->name,
                        $diseasName,
                        $treatmentStatus,
                        $startDate,
                        $endDate,
                        $numberOfExercise,
                        $numberOfcompletedExercise,
                        $averageExercise,
                        $totalPainLevel,
                        $averagePainLevel,
                        $totalSatisfactionDailyGoal,
                        $averageDailyGoal,
                        $totalSatisfactionWeeklyGoal,
                        $averageWeeklyGoal,
                        $questionnResultAtStart,
                        $questionnResultAtEnd,
                    ]);

                    $patientTreatmentPlanData[] = $treatmentPlanData;


                    // Get treatment survey data
                    $patientTreatmentSurveys = self::getPatientTreatmentSurveys($treatmentPlan->id, $patient->id);
                    if (count($patientTreatmentSurveys) > 0) {
                        foreach($patientTreatmentSurveys as $patientTreatmentSurvey) {
                            $treatmentInitialSurvey = UserSurvey::where('user_id', $patient->id)
                                ->where('survey_id', $patientTreatmentSurvey->id)
                                ->where('treatment_plan_id', $treatmentPlan->id)
                                ->where('survey_phase', UserSurvey::SURVEY_PHASE_START)
                                ->first();
                            $treatmentFinalSurvey = UserSurvey::where('user_id', $patient->id)
                                ->where('survey_id', $patientTreatmentSurvey->id)
                                ->where('treatment_plan_id', $treatmentPlan->id)
                                ->where('survey_phase', UserSurvey::SURVEY_PHASE_END)
                                ->first();

                            $initailResult = self::getSurveyResult($treatmentInitialSurvey?->answer);
                            $finalResult = self::getSurveyResult($treatmentFinalSurvey?->answer);
                            $surveyTitle = $patientTreatmentSurvey->questionnaire->getTranslation('title', $language?->code ?? 'en');
                            $patientTreatmentPlanSurveyData[] = array_merge($patientData, [
                                $treatmentPlan->name,
                                $diseasName,
                                $treatmentStatus,
                                $startDate,
                                $endDate,
                                $surveyTitle,
                                $initailResult,
                                $finalResult
                            ]);
                        }
                        
                    } else {
                        $patientTreatmentPlanSurveyData[] = array_merge($patientData, [
                            $treatmentPlan->name,
                            $diseasName,
                            $treatmentStatus,
                            $startDate,
                            $endDate,
                        ]);
                    }
                    
                }
            } else {
                $patientTreatmentPlanData[] = $patientData;
                $patientTreatmentPlanSurveyData[] = $patientData;
            }
        }

        $spreadsheet = new Spreadsheet();
        $patientTreatmentSheet = $spreadsheet->getActiveSheet();
        $patientTreatmentSheet->setTitle($translations['report.patient_raw_data.patient_treatment']);
        $patientTreatmentTemplate = new PatientTreatmentTemplate();
        $patientTreatmentTemplate->template($patientTreatmentPlanData, $patientTreatmentSheet, $translations);

        $patientAssistiveSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $translations['report.patient_raw_data.patient_assistive_technology']);
        $spreadsheet->addSheet($patientAssistiveSheet);
        $patientAssistiveTemplate = new PatientAssistiveTemplate();
        $patientAssistiveTemplate->template($patientAssistiveData, $patientAssistiveSheet, $translations);

        $patientTreatmentPlanSurveySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $translations['report.patient_raw_data.patient_treatment_survey']);
        $spreadsheet->addSheet($patientTreatmentPlanSurveySheet);
        $patientTreatmentPlanSurveyTemplate = new PatientTreatmentPlanSurveyTemplate();
        $patientTreatmentPlanSurveyTemplate->template($patientTreatmentPlanSurveyData, $patientTreatmentPlanSurveySheet, $translations);

        $patientSurveySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $translations['report.patient_raw_data.patient_survey']);
        $spreadsheet->addSheet($patientSurveySheet);
        $patientSurveyTemplate = new PatientSurveyTemplate();
        $patientSurveyTemplate->template($patientSurveyData, $patientSurveySheet, $translations);
        
        // Set the first sheet as the active sheet
        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $fileName = 'Questionnaire_Answers_' . date('Y-m-d_His') . '.xlsx';
        $filePath = $absolutePath . '/' . $fileName;

        $writer->save($filePath);
        return $basePath . '/' . $fileName;
    }

    public static function getTreatmentPlanStatus($startDate, $endDate, $translations)
    {
        $status = null;
        $now = Carbon::now()->format('Y-m-d');
        if ($endDate < $now) {
            $status = $translations['common.finished'];
        } else if ($startDate <= $now && $endDate >= $now) {
            $status = $translations['common.on_going'];
        } else {
            $status = $translations['common.planned'];
        }
        return $status;
    }

    public static function getQuestionnaireResult($questionnaire, $answers)
    {
        $totalAnswerValue = 0;
        foreach ($questionnaire->questions as $question) {
            $answer = null;
            foreach ($answers as $answer) {
                if ($answer->question_id === $question->id) {
                    $answer = $answer->answer;
                    break;
                }
            }

            if ($answer) {
                if ($question->type === Question::QUESTION_TYPE_CHECKBOX) {
                    $foundAnswers = $question->answers->filter(fn($questionAnswer) => in_array($questionAnswer->id, $answer))->toArray();
                    $totalAnswerValue += array_sum(array_column($foundAnswers, 'value'));
                } else if ($question->type === Question::QUESTION_TYPE_MULTIPLE) {
                    $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->id === $answer);
                    $totalAnswerValue += $foundAnswer?->value ?? 0;
                } else if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                    $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->question_id === $question->id);
                    $totalAnswerValue += $foundAnswer?->value ?? 0;
                }
            }
        }
        return $totalAnswerValue;
    }

    public static function getSurveyResult($answers)
    {
        $totalAnswerValue = 0;
        if ($answers) {
            foreach ($answers as $answer) {
                $question = Question::find($answer['question_id']);
                if ($question) {
                    if ($question->type === Question::QUESTION_TYPE_CHECKBOX) {
                        $foundAnswers = $question->answers->filter(fn($questionAnswer) => in_array($questionAnswer->id, $answer['answer']))->toArray();
                        $totalAnswerValue += array_sum(array_column($foundAnswers, 'value'));
                    } else if ($question->type === Question::QUESTION_TYPE_MULTIPLE) {
                        $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->id === $answer['answer']);
                        $totalAnswerValue += $foundAnswer?->value ?? 0;
                    } else if ($question->type === Question::QUESTION_TYPE_OPEN_NUMBER) {
                        $foundAnswer = $question->answers->first(fn($questionAnswer) => $questionAnswer->question_id === $question->id);
                        $totalAnswerValue += $foundAnswer?->value ?? 0;
                    }
                }
            }
        }

        return $totalAnswerValue;
    }

    public static function getPatientAssistiveTechnologyData($patientAssistiveTechnologies, $patientData)
    {
        $data = [];
        if (count((array) $patientAssistiveTechnologies) > 0) {
            foreach ($patientAssistiveTechnologies as $index => $assistiveTechnology) {
                $assistive = AssistiveTechnology::find($assistiveTechnology->assistive_technology_id);
                $provisionDate = Carbon::createFromFormat('d/m/Y', $assistiveTechnology->provision_date);
                $assistiveData = array_merge($patientData, [
                    $assistive?->getTranslation('name', $language?->code ?? 'en'),
                    $provisionDate->format('Y-m-d')
                ]);
                $data[] = $assistiveData;
            }
        } else {
            $data[] = $patientData;
        }

        return $data;
    }

    public static function getPatientSurveyData($patientId, $patientData)
    {
        $data = [];
        $patientSurveys = Survey::join('user_surveys', 'surveys.id', 'user_surveys.survey_id')
            ->where('surveys.role', User::GROUP_PATIENT)
            ->where('surveys.include_at_the_start', 0)
            ->where('surveys.include_at_the_end', 0)
            ->where('surveys.status', '<>', Survey::STATUS_DRAFT)
            ->where('user_surveys.user_id', $patientId)
            ->select('surveys.*', 'user_surveys.answer')
            ->get();
        if (count($patientSurveys) > 0) {
            foreach($patientSurveys as $patientSurvey) {
                $surveyTitle = $patientSurvey->questionnaire->getTranslation('title', $language?->code ?? 'en');
                $result = self::getSurveyResult(json_decode($patientSurvey->answer, true));
                $data[] = array_merge($patientData, [
                    $surveyTitle,
                    $result
                ]);
            }
        } else {
            $data[] = $patientData;
        }

        return $data;
    }

    public static function getPatientTreatmentSurveys($treatmentPlanId, $patientId)
    {
        $patientTreatmentSurveys = Survey::join('user_surveys', 'surveys.id', 'user_surveys.survey_id')
            ->where('surveys.role', User::GROUP_PATIENT)
            ->where(function ($query) {
                $query->where('surveys.include_at_the_start', 1)
                    ->orWhere('surveys.include_at_the_end', 1);
            })
            ->where('surveys.status', '<>', Survey::STATUS_DRAFT)
            ->where('user_surveys.treatment_plan_id',$treatmentPlanId)
            ->where('user_surveys.user_id', $patientId)
            ->select('surveys.*')
            ->distinct('user_surveys.survey_id')
            ->get();
        return $patientTreatmentSurveys;
    }
}
