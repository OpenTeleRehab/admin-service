<?php

namespace App\Exports;

use App\Models\HealthCondition;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Models\Forwarder;
use App\Models\Clinic;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Helpers\TranslationHelper;
use App\Models\AssistiveTechnology;
use App\Models\Country;
use App\Models\Language;
use App\Models\Questionnaire;
use App\Models\Question;
use App\Models\UserSurvey;
use App\Exports\Templates\PatientTreatmentTemplate;
use App\Exports\Templates\PatientAssistiveTemplate;
use App\Exports\Templates\PatientSurveyTemplate;
use App\Exports\Templates\PatientTreatmentPlanSurveyTemplate;
use App\Exports\Templates\PatientQuestionnaireStartEndResultTemplate;
use App\Models\PhcService;
use App\Models\Province;
use App\Models\Region;
use App\Models\Survey;
use App\Models\User;

class PatientRawDataExport
{
    /**
     * @var string $exportDirectoryName
     */
    protected static $exportDirectoryName = 'exports/';

    private static function getUniqueIds($collection, $field) {
        return collect($collection)
            ->pluck($field)
            ->unique()
            ->filter()
            ->values();
    }

    /**
     * Exports data to an XLSX file.
     *
     * @param $payload.
     * @return string The path to the exported file.
     */
    public static function export($payload)
    {
        $translations = TranslationHelper::getTranslations($payload['lang']);
        $language = Language::find($payload['lang']);
        $basePath = 'app/' . self::$exportDirectoryName;
        $absolutePath = storage_path($basePath);
        $hosts = config('settings.hosting_country');

        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }

        $patients = [];
        $therapists = [];

        if ($payload['user_type'] === User::ADMIN_GROUP_ORG_ADMIN) {
            // Get patient data from other host
            foreach ($hosts as $host) {
                $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $host);
                $response = Http::withHeaders(['Authorization' => 'Bearer ' . $access_token, 'country' => $host])->get(env('PATIENT_SERVICE_URL') . '/patient/list/get-raw-data', $payload);

                if ($response && $response->successful()) {
                    $response = json_decode($response);
                    $patients = array_merge($patients, $response->data);
                }
            }

            // Get patient data from global host
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

            $response = Http::withToken($access_token)->get(env('PATIENT_SERVICE_URL') . '/patient/list/get-raw-data', $payload);

            if ($response && $response->successful()) {
                $response = json_decode($response);
                $patients = array_merge($patients, $response->data);
            }
        } else {
            $country = Country::find($payload['country']);
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country->iso_code);
            $response = Http::withToken($access_token)->withHeaders([
                'country' => $country->iso_code
            ])->get(env('PATIENT_SERVICE_URL') . '/patient/list/get-raw-data', $payload);
            if (!empty($response) && $response->successful()) {
                $response = json_decode($response);
                $patients = $response->data;
            }
        }

        // Get therapist data
        $uniqueTherapistIds = array_values(array_unique(array_merge(
            array_column($patients, 'therapist_id'),
            ...array_map(fn($patient) => $patient->secondary_therapists, $patients)
        )));
        $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
        $response = Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . '/patient/therapist-by-ids', [
            'ids' => json_encode($uniqueTherapistIds),
        ]);

        if (!empty($response) && $response->successful()) {
            $therapists = json_decode($response);
        }

        $patientTreatmentPlanData = [];
        $patientTreatmentPlanSurveyData = [];
        $patientTreatmentQuestionnaireStartEndData = [];
        $patientAssistiveData = [];
        $patientSurveyData = [];

        $countryIds = self::getUniqueIds($patients, 'country_id');
        $regionIds = self::getUniqueIds($patients, 'region_id');
        $provinceIds = self::getUniqueIds($patients, 'province_id');
        $clinicIds = self::getUniqueIds($patients, 'clinic_id');
        $phcServiceIds = self::getUniqueIds($patients, 'phc_service_id');

        $countryNeeds = Country::whereIn('id', $countryIds)->get()->keyBy('id');
        $regionNeeds = Region::whereIn('id', $regionIds)->get()->keyBy('id');
        $provinceNeeds = Province::whereIn('id', $provinceIds)->get()->keyBy('id');
        $clinicNeeds = Clinic::whereIn('id', $clinicIds)->get()->keyBy('id');
        $phcServiceNeeds = PhcService::whereIn('id', $phcServiceIds)->get()->keyBy('id');
        $therapistUserNeeds = collect($therapists?->data)->keyBy('id');

        foreach ($patients as $patient) {
            $country = $countryNeeds[$patient->country_id] ?? null;
            $region = $regionNeeds[$patient->region_id] ?? null;
            $province = $provinceNeeds[$patient->province_id] ?? null;
            $clinic = $clinicNeeds[$patient->clinic_id] ?? null;
            $phcService = $phcServiceNeeds[$patient->phc_service_id] ?? null;
            $age = Carbon::parse($patient->date_of_birth)->age;
            $dob = Carbon::parse($patient->date_of_birth)->toDateString();
            $status = $patient->enabled === 1 ? $translations['common.active'] : $translations['common.inactive'];
            $location = $translations['common.' . $patient->location];
            $gender = $translations[$patient->gender];
            $therapistName = isset($therapistUserNeeds[$patient->therapist_id])
                ? $therapistUserNeeds[$patient->therapist_id]->first_name . ' ' . $therapistUserNeeds[$patient->therapist_id]->last_name
                : null;
            $secondaryTherapistsNames = collect($patient->secondary_therapists)
                ->map(fn($id) =>
                    isset($therapistUserNeeds[$id])
                        ? $therapistUserNeeds[$id]->first_name . ' ' . $therapistUserNeeds[$id]->last_name
                        : null
                )
                ->filter()
                ->values()
                ->join(', ');
            $leadPhcWorkerName = isset($therapistUserNeeds[$patient->phc_worker_id])
                ? $therapistUserNeeds[$patient->phc_worker_id]->first_name . ' ' . $therapistUserNeeds[$patient->phc_worker_id]->last_name
                : null;
            $supplementaryPhcWorkers = collect($patient->supplementary_phc_workers)
                ->map(fn($id) =>
                    isset($therapistUserNeeds[$id])
                        ? $therapistUserNeeds[$id]->first_name . ' ' . $therapistUserNeeds[$id]->last_name
                        : null
                )
                ->filter()
                ->values()
                ->join(', ');
            $patientData = [
                $clinic?->name,
                $phcService?->name,
                $patient->identity,
                $country?->name,
                $region?->name,
                $province?->name,
                $gender,
                $dob,
                $age,
                $status,
                $location,
                $therapistName,
                $secondaryTherapistsNames,
                $leadPhcWorkerName,
                $supplementaryPhcWorkers,
                $patient->call,
            ];

            $patientAssistiveData = array_merge($patientAssistiveData, self::getPatientAssistiveTechnologyData($patient->assistiveTechnologies, $patientData));
            $patientSurveyData = array_merge($patientSurveyData, self::getPatientSurveyData($patient->id, $patientData));

            if (count((array) $patient->treatmentPlans) > 0) {
                foreach ($patient->treatmentPlans as $index => $treatmentPlan) {
                    $healthCondition = HealthCondition::find($treatmentPlan->health_condition_id);

                    if (!$healthCondition) {
                        continue;
                    }

                    $healthConditionName = $healthCondition?->getTranslation('title', $language?->code ?? 'en');
                    $healthConditionGroup = $healthCondition->parent;
                    $healthConditionGroupName = $healthConditionGroup?->getTranslation('title', $language?->code ?? 'en');
                    $startDate = Carbon::createFromFormat('d/m/Y', $treatmentPlan->start_date)->format('Y-m-d');
                    $endDate = Carbon::createFromFormat('d/m/Y', $treatmentPlan->end_date)->format('Y-m-d');
                    $treatmentStatus = self::getTreatmentPlanStatus($startDate, $endDate, $translations);

                    $treatmentPlanData = array_merge($patientData, [
                        $treatmentPlan->name,
                        $healthConditionGroupName,
                        $healthConditionName,
                        $treatmentStatus,
                        $startDate,
                        $endDate,
                        round($treatmentPlan->initialAverageAdherence ?? 0, 2),
                        round($treatmentPlan->finalAverageAdherence ?? 0, 2),
                        round($treatmentPlan->initialAveragePainLevel ?? 0, 2),
                        round($treatmentPlan->finalAveragePainLevel ?? 0, 2),
                        round($treatmentPlan->initialAverageDailyGoal ?? 0, 2),
                        round($treatmentPlan->finalAverageDailyGoal ?? 0, 2),
                        round($treatmentPlan->averageDailyGoal ?? 0, 2),
                        round($treatmentPlan->initialAverageWeeklyGoal ?? 0, 2),
                        round($treatmentPlan->finalAverageWeeklyGoal ?? 0, 2),
                        round($treatmentPlan->averageWeeklyGoal ?? 0, 2)
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
                                $healthConditionGroupName,
                                $healthConditionName,
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
                            $healthConditionGroupName,
                            $healthConditionName,
                            $treatmentStatus,
                            $startDate,
                            $endDate,
                        ]);
                    }

                    // Get questionnaire that include at start and end
                    if (count((array) $treatmentPlan->questionnaires) > 0) {
                        foreach ($treatmentPlan->questionnaires as $questionnaire) {
                            $foundQuestionnaire = Questionnaire::find($questionnaire->id);
                            if ($foundQuestionnaire && ($foundQuestionnaire->include_at_the_start || $foundQuestionnaire->include_at_the_end)) {
                                $phase = match (true) {
                                    $foundQuestionnaire->include_at_the_start && !$foundQuestionnaire->include_at_the_end => $translations['questionnaire.include_at_the_start'],
                                    !$foundQuestionnaire->include_at_the_start && $foundQuestionnaire->include_at_the_end => $translations['questionnaire.include_at_the_end'],
                                    $foundQuestionnaire->include_at_the_start && $foundQuestionnaire->include_at_the_end && $questionnaire->day === 1 => $translations['questionnaire.include_at_the_start'],
                                    $foundQuestionnaire->include_at_the_start && $foundQuestionnaire->include_at_the_end && $questionnaire->day !== 1 => $translations['questionnaire.include_at_the_end'],
                                    default => '',
                                };

                                $patientTreatmentQuestionnaireStartEndData[] = array_merge($patientData, [
                                    $treatmentPlan->name,
                                    $healthConditionGroupName,
                                    $healthConditionName,
                                    $treatmentStatus,
                                    $startDate,
                                    $endDate,
                                    $foundQuestionnaire->getTranslation('title', $language?->code ?? 'en'),
                                    $phase,
                                    self::getQuestionnaireResult($foundQuestionnaire, $questionnaire->answer)
                                ]);
                            }
                        }
                    } else {
                        $patientTreatmentQuestionnaireStartEndData[] = array_merge($patientData, [
                            $treatmentPlan->name,
                            $healthConditionGroupName,
                            $healthConditionName,
                            $treatmentStatus,
                            $startDate,
                            $endDate,
                        ]);
                    }
                }
            } else {
                $patientTreatmentPlanData[] = $patientData;
                $patientTreatmentPlanSurveyData[] = $patientData;
                $patientTreatmentQuestionnaireStartEndData[] = $patientData;
            }
        }

        $spreadsheet = new Spreadsheet();
        $patientTreatmentSheet = $spreadsheet->getActiveSheet();
        $patientTreatmentSheet->setTitle($translations['report.patient_raw_data.patient_treatment']);
        $patientTreatmentTemplate = new PatientTreatmentTemplate();
        $patientTreatmentTemplate->template($patientTreatmentPlanData, $patientTreatmentSheet, $translations);

        $patientTreatmentQuestionnaireStartEndSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $translations['report.patient_raw_data.patient_treatment_questionnaire_start_end']);
        $spreadsheet->addSheet($patientTreatmentQuestionnaireStartEndSheet);
        $patientTreatmentQuestionnaireStartEndTemplate = new PatientQuestionnaireStartEndResultTemplate();
        $patientTreatmentQuestionnaireStartEndTemplate->template($patientTreatmentQuestionnaireStartEndData, $patientTreatmentQuestionnaireStartEndSheet, $translations);

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
        $fileName = 'Patient-Raw-Data-' . date('Y-m-d_His') . '.xlsx';
        $filePath = $absolutePath . $fileName;

        $writer->save($filePath);
        return $basePath . $fileName;
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
            foreach ($answers as $patientAnswer) {
                if ($patientAnswer->question_id === $question->id) {
                    $answer = $patientAnswer->answer;
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
