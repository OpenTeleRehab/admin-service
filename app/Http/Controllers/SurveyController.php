<?php

namespace App\Http\Controllers;

use App\Exports\SurveyExport;
use App\Helpers\SurveyHelper;
use App\Http\Resources\SurveyResource;
use App\Models\Survey;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Clinic;
use App\Models\Country;
use App\Models\Organization;
use App\Models\UserSurvey;
use Illuminate\Support\Facades\Auth;

class SurveyController extends Controller
{
    const SUPER_ADMIN = 'super_admin';
    const ORGANIZATION_ADMIN = 'organization_admin';
    const COUNTRY_ADMIN = 'country_admin';
    const CLINIC_ADMIN = 'clinic_admin';

    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->type === self::SUPER_ADMIN) {
            $surveys = $this->getSurveysForSuperAdmin()->get();
        }

        if ($user->type === self::ORGANIZATION_ADMIN) {
            $surveys = $this->getSurveysForOrganizationAdmin()->get();
        }

        if ($user->type === self::COUNTRY_ADMIN) {
            $surveys = $this->getSurveysForCountryAdmin()->get();
        }

        if ($user->type === self::CLINIC_ADMIN) {
            $surveys = $this->getSurveysForClinicAdmin()->get();
        }

        return ['success' => true, 'data' => SurveyResource::collection($surveys)];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function store(Request $request, QuestionnaireController $questionnaireController)
    {
        $startDate = null;
        $endDate = null;
        if ($request->get('start_date')) {
            $startDate = date_create_from_format('d/m/Y', $request->get('start_date'));
            $startDate = date_format($startDate, config('settings.defaultTimestampFormat'));
        }

        if ($request->get('end_date')) {
            $endDate = date_create_from_format('d/m/Y', $request->get('end_date'));
            $endDate = date_format($endDate, config('settings.defaultTimestampFormat'));
        }

        $newRequest = new Request(['data' => $request->get('questionnaire')]);
        $response = $questionnaireController->store($newRequest);
        if ($response['success'] === true) {
            $user = Auth::user();
            $organization = Organization::where('sub_domain_name', env('APP_NAME'))->firstOrFail();

            $surveyData = [
                'role' => $request->get('role'),
                'country' => json_decode($request->get('country'), true),
                'gender' => json_decode($request->get('gender'), true),
                'location' => json_decode($request->get('location'), true),
                'clinic' => json_decode($request->get('clinic'), true),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'questionnaire_id' => $response['id'],
                'include_at_the_start' => $request->boolean('include_at_the_start') ?? false,
                'include_at_the_end' => $request->boolean('include_at_the_end') ?? false,
                'status' => Survey::STATUS_DRAFT,
                'frequency' => $request->string('frequency'),
            ];

            if ($user->type === self::SUPER_ADMIN && env('APP_NAME') == 'hi') {
                $surveyData['organization'] = json_decode($request->get('organization'), true);
                $surveyData['global'] = true;
            } else {
                $surveyData['organization'] = [$organization->id];
                $surveyData['global'] = false;
            }

            if ($user->type === self::COUNTRY_ADMIN) {
                $surveyData['country'] = [(int)$user->country_id];
            }

            if ($user->type === self::CLINIC_ADMIN) {
                $surveyData['country'] = [(int)$user->country_id];
                $surveyData['clinic'] = [(int)$user->clinic_id];
            }

            Survey::create($surveyData);
            return ['success' => true, 'message' => 'success_message.survey_add'];
        } else {
            return ['success' => false, 'message' => 'error_message.survey_add'];
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Survey  $survey
     *
     * @return \App\Http\Resources\SurveyResource
     */
    public function show(Survey $survey)
    {
        return new SurveyResource($survey);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Survey  $survey
     *
     * @return array
     */
    public function update(Request $request, Survey $survey, QuestionnaireController $questionnaireController)
    {
        $startDate = null;
        $endDate = null;
        if ($request->get('start_date')) {
            $startDate = date_create_from_format('d/m/Y', $request->get('start_date'));
            $startDate = date_format($startDate, config('settings.defaultTimestampFormat'));
        }

        if ($request->get('end_date')) {
            $endDate = date_create_from_format('d/m/Y', $request->get('end_date'));
            $endDate = date_format($endDate, config('settings.defaultTimestampFormat'));
        }

        $newRequest = new Request(['data' => $request->get('questionnaire')]);
        $response = $questionnaireController->update($newRequest, $survey->questionnaire);
        if ($response['success'] === true) {
            $user = Auth::user();
            $organization = Organization::where('sub_domain_name', env('APP_NAME'))->firstOrFail();

            $surveyData = [
                'role' => $request->get('role'),
                'country' => json_decode($request->get('country'), true),
                'gender' => json_decode($request->get('gender'), true),
                'location' => json_decode($request->get('location'), true),
                'clinic' => json_decode($request->get('clinic'), true),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'include_at_the_start' => $request->boolean('include_at_the_start'),
                'include_at_the_end' => $request->boolean('include_at_the_end'),
                'frequency' => $request->string('frequency'),
            ];

            if ($user->type === self::SUPER_ADMIN && env('APP_NAME') == 'hi') {
                $surveyData['organization'] = json_decode($request->get('organization'), true);
                $surveyData['global'] = true;
            } else {
                $surveyData['organization'] = [$organization->id];
                $surveyData['global'] = false;
            }

            if ($user->type === self::COUNTRY_ADMIN) {
                $surveyData['country'] = [(int)$user->country_id];
            }

            if ($user->type === self::CLINIC_ADMIN) {
                $surveyData['country'] = [(int)$user->country_id];
                $surveyData['clinic'] = [(int)$user->clinic_id];
            }

            $survey->update($surveyData);
            return ['success' => true, 'message' => 'success_message.survey_update'];
        } else {
            return ['success' => false, 'message' => 'error_message.survey_update'];
        }
    }

    /**
     * Get surveys for Super Admin.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getSurveysForSuperAdmin()
    {
        return Survey::where('global', true);
    }

    /**
     * Get surveys for Organization Admin.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getSurveysForOrganizationAdmin()
    {
        $userIds = User::where('type', self::ORGANIZATION_ADMIN)->pluck('id');
        return Survey::whereIn('author', $userIds);
    }

    /**
     * Get surveys for Country Admin.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getSurveysForCountryAdmin()
    {
        $user = Auth::user();
        $country = Country::firstWhere('id', $user->country_id);
        $userIds = User::where('country_id', $country->id)
                    ->where('type', self::COUNTRY_ADMIN)
                    ->pluck('id');
        return Survey::whereIn('author', $userIds);
    }

    /**
     * Get surveys for Clinic Admin.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getSurveysForClinicAdmin()
    {
        $user = Auth::user();
        $clinic = Clinic::firstWhere('id', $user->clinic_id);
        $userIds = User::where('clinic_id', $clinic->id)
                    ->where('type', self::CLINIC_ADMIN)
                    ->pluck('id');
        return Survey::whereIn('author', $userIds);
    }

    /**
     * @param \App\Models\Survey  $survey
     *
     * @return array
     */
    public function publish(Survey $survey)
    {
        $user = Auth::user();

        if ($user->type === self::SUPER_ADMIN) {
            $surveys = $this->getSurveysForSuperAdmin();
        }

        if ($user->type === self::ORGANIZATION_ADMIN) {
            $surveys = $this->getSurveysForOrganizationAdmin();
        }

        if ($user->type === self::COUNTRY_ADMIN) {
            $surveys = $this->getSurveysForCountryAdmin();
        }

        if ($user->type === self::CLINIC_ADMIN) {
            $surveys = $this->getSurveysForClinicAdmin();
        }
        // Update the all previous published survey to expired.
        $surveys->where('status', Survey::STATUS_PUBLISHED)->where('role', $survey->role)
            ->update(['status' => Survey::STATUS_EXPIRED]);
        // Set the current survey to published.
        $survey->update([
            'status' => Survey::STATUS_PUBLISHED,
            'published_date' => Carbon::now()
        ]);

        return ['success' => true, 'message' => 'success_message.survey_publish'];
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function getPublishSurveyByUserType(Request $request)
    {
        $type = $request->get('type');
        $survey = null;
        $organization = Organization::where('sub_domain_name', $request->get('organization'))->first();
        switch ($type) {
            case User::ADMIN_GROUP_ORG_ADMIN:
                $survey = Survey::where('role', $type)
                    ->leftJoin('user_surveys', function ($join) use ($request) {
                        $join->on('surveys.id', '=', 'user_surveys.survey_id')
                            ->where('user_surveys.user_id', '=', $request->integer('user_id'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('user_surveys.id')
                            ->orWhereNull('user_surveys.answer');
                    })
                    ->whereJsonContains('organization', $organization?->id)
                    ->whereDate('start_date', '<=', Carbon::now())
                    ->whereDate('end_date', '>=', Carbon::now())
                    ->where('surveys.status', Survey::STATUS_PUBLISHED)
                    ->select('surveys.*')
                    ->get();
                break;
            case User::ADMIN_GROUP_COUNTRY_ADMIN:
                $survey = Survey::where('role', $type)
                    ->leftJoin('user_surveys', function ($join) use ($request) {
                        $join->on('surveys.id', '=', 'user_surveys.survey_id')
                            ->where('user_surveys.user_id', '=', $request->integer('user_id'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('user_surveys.id')
                            ->orWhereNull('user_surveys.answer');
                    })
                    ->whereJsonContains('organization', $organization?->id)
                    ->whereDate('start_date', '<=', Carbon::now())
                    ->whereDate('end_date', '>=', Carbon::now())
                    ->where('surveys.status', Survey::STATUS_PUBLISHED)
                    ->whereJsonContains('country', $request->integer('country_id'))
                    ->select('surveys.*')
                    ->get();
                break;
            case User::ADMIN_GROUP_CLINIC_ADMIN:
                $survey = Survey::where('role', $type)
                    ->leftJoin('user_surveys', function ($join) use ($request) {
                        $join->on('surveys.id', '=', 'user_surveys.survey_id')
                            ->where('user_surveys.user_id', '=', $request->integer('user_id'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('user_surveys.id')
                            ->orWhereNull('user_surveys.answer');
                    })
                    ->whereJsonContains('organization', $organization?->id)
                    ->whereDate('start_date', '<=', Carbon::now())
                    ->whereDate('end_date', '>=', Carbon::now())
                    ->where('surveys.status', Survey::STATUS_PUBLISHED)
                    ->whereJsonContains('country', $request->integer('country_id'))
                    ->whereJsonContains('clinic', $request->integer('clinic_id'))
                    ->select('surveys.*')
                    ->get();
                break;
            case User::GROUP_THERAPIST:
                $survey = Survey::where('role', $type)
                    ->leftJoin('user_surveys', function ($join) use ($request) {
                        $join->on('surveys.id', '=', 'user_surveys.survey_id')
                            ->where('user_surveys.user_id', '=', $request->integer('user_id'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('user_surveys.id')
                            ->orWhereNull('user_surveys.answer');
                    })
                    ->whereJsonContains('organization', $organization?->id)
                    ->whereDate('start_date', '<=', Carbon::now())
                    ->whereDate('end_date', '>=', Carbon::now())
                    ->where('surveys.status', Survey::STATUS_PUBLISHED)
                    ->whereJsonContains('country', $request->integer('country_id'))
                    ->whereJsonContains('clinic', $request->integer('clinic_id'))
                    ->select('surveys.*')
                    ->get();
                break;
            case User::GROUP_PATIENT:
                $treatmentSurvey = Survey::where('role', $type)
                    ->leftJoin('user_surveys', function ($join) use ($request) {
                        $join->on('surveys.id', '=', 'user_surveys.survey_id')
                            ->where('user_surveys.user_id', $request->integer('user_id'))
                            ->where('user_surveys.treatment_plan_id', $request->integer('treatment_plan_id'))
                            ->where('user_surveys.survey_phase', $request->get('survey_phase'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('user_surveys.id')
                            ->orWhereNull('user_surveys.answer');
                    })
                    ->whereJsonContains('organization', $organization?->id)
                    ->whereJsonContains('location', $request->get('location'))
                    ->whereJsonContains('gender', $request->get('gender'))
                    ->where('surveys.status', Survey::STATUS_PUBLISHED)
                    ->whereJsonContains('country', $request->integer('country_id'))
                    ->whereJsonContains('clinic', $request->integer('clinic_id'))
                    ->where(function ($query) {
                        $query->where('surveys.include_at_the_start', 1)
                            ->orWhere('surveys.include_at_the_end', 1);
                    })
                    ->select('surveys.*')
                    ->get();

                $generalSurvey = Survey::where('role', $type)
                    ->leftJoin('user_surveys', function ($join) use ($request) {
                        $join->on('surveys.id', '=', 'user_surveys.survey_id')
                            ->where('user_surveys.user_id', '=', $request->integer('user_id'));
                    })
                    ->where(function ($query) {
                        $query->whereNull('user_surveys.id')
                            ->orWhereNull('user_surveys.answer');
                    })
                    ->whereJsonContains('organization', $organization?->id)
                    ->whereDate('start_date', '<=', Carbon::now())
                    ->whereDate('end_date', '>=', Carbon::now())
                    ->whereJsonContains('location', $request->get('location'))
                    ->whereJsonContains('gender', $request->get('gender'))
                    ->where('surveys.status', Survey::STATUS_PUBLISHED)
                    ->whereJsonContains('country', $request->integer('country_id'))
                    ->whereJsonContains('clinic', $request->integer('clinic_id'))
                    ->select('surveys.*')
                    ->get();

                $survey = $generalSurvey->merge($treatmentSurvey);
                break;
            default:
                return ['success' => false, 'data' => []];
        }
        return ['success' => true, 'data' => SurveyResource::collection($survey)];
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function submit(Request $request)
    {
        $survey = Survey::find($request->integer('survey_id'));
        if ($survey->role === User::GROUP_PATIENT && ($survey->include_at_the_start || $survey->include_at_the_end)) {
            $userSurvey = UserSurvey::updateOrCreate(
                [
                    'user_id' => $request->integer('user_id'),
                    'survey_id' => $request->integer('survey_id'),
                    'treatment_plan_id' => $request->integer('treatment_plan_id'),
                    'survey_phase' => $request->string('survey_phase'),
                ],
                [
                    'answer' => json_decode($request->get('answers')),
                    'status' => UserSurvey::STATUS_COMPLETED,
                    'completed_at' => Carbon::now(),
                    'survey_phase' => $request->string('survey_phase'),
                ]);
        } else {
            $userSurvey = UserSurvey::updateOrCreate(
                [
                    'user_id' => $request->integer('user_id'),
                    'survey_id' => $request->integer('survey_id'),
                ],
                [
                    'answer' => json_decode($request->get('answers')),
                    'status' => UserSurvey::STATUS_COMPLETED,
                    'completed_at' => Carbon::now(),
                ]);
        }

        $totalScore = SurveyHelper::getTotalScore($userSurvey);
        $userSurvey->update(['score' => $totalScore]);

        return ['success' => true, 'message' => 'success_message.survey_submitted'];
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function skipSurvey(Request $request)
    {
        UserSurvey::updateOrCreate(
            [
                'user_id' => $request->integer('user_id'),
                'survey_id' => $request->integer('survey_id'),
            ],
            [
                'status' => UserSurvey::STATUS_SKIPPED,
                'skipped_at' => Carbon::now(),
            ]);

        return ['success' => true, 'message' => 'success_message.survey_skipped'];
    }
}
