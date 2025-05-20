<?php

namespace App\Http\Controllers;

use App\Http\Resources\GlobalPatientResource;
use App\Models\Country;
use App\Models\Forwarder;
use App\Models\GlobalPatient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class GlobalPatientController extends Controller
{
    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $info = [];

        if (isset($data['id'])) {
            $users = GlobalPatient::where('patient_id', $data['id'])->get();
        } else {
            $query = GlobalPatient::query();

            if (isset($data['therapist_id'])) {
                $query->where(function ($query) use ($data) {
                    $query->where('therapist_id', $data['therapist_id'])->orWhereJsonContains('secondary_therapists', intval($data['therapist_id']));
                });
            }

            if (isset($data['enabled'])) {
                $query->where('enabled', boolval($data['enabled']));
            }

            if (isset($data['country'])) {
                $query->where('country_id', $data['country']);
            }

            if (isset($data['clinic'])) {
                $query->where('clinic_id', $data['clinic']);
            }

            if (isset($data['search_value'])) {
                if ($request->get('type') === GlobalPatient::ADMIN_GROUP_ORG_ADMIN) {
                    $query->where(function ($query) use ($data) {
                        $query->where('identity', 'like', '%' . $data['search_value'] . '%');
                    });
                } else {
                    $query->where(function ($query) use ($data) {
                        $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                            ->orWhereHas('treatmentPlans', function (Builder $query) use ($data) {
                                $query->where('name', 'like', '%' . $data['search_value'] . '%');
                            });
                    });
                }
            }

            if (isset($data['filters'])) {
                $filters = $request->get('filters');
                $therapist_id = $data['therapist_id'] ?? '';
                $query->where(function ($query) use ($filters, $therapist_id) {
                    foreach ($filters as $filter) {
                        $filterObj = json_decode($filter);
                        if ($filterObj->columnName === 'date_of_birth') {
                            $dateOfBirth = date_create_from_format('d/m/Y', $filterObj->value);
                            $query->where('date_of_birth', date_format($dateOfBirth, config('settings.defaultTimestampFormat')));
                        } elseif (($filterObj->columnName === 'region' || $filterObj->columnName === 'clinic') && $filterObj->value !== '') {
                            $query->where('clinic_id', $filterObj->value);
                        } elseif ($filterObj->columnName === 'country' && $filterObj->value !== '') {
                            $query->where('country_id', $filterObj->value);
                        } elseif ($filterObj->columnName === 'treatment_status') {
                            $today = Carbon::today();
                            if ($filterObj->value == GlobalPatient::ONGOING_TREATMENT_PLAN) {
                                $query->whereHas('treatmentPlans', function (Builder $query) use ($today) {
                                    $query->whereDate('start_date', '<=', $today)
                                        ->whereDate('end_date', '>=', $today);
                                });
                            } elseif ($filterObj->value == GlobalPatient::PLANNED_TREATMENT_PLAN) {
                                $query->whereHas('treatmentPlans', function (Builder $query) use ($today) {
                                    $query->whereDate('start_date', '>', $today)
                                        ->whereDate('end_date', '>', $today);
                                })->whereDoesntHave('treatmentPlans', function (Builder $query) use ($today) {
                                    $query->whereDate('start_date', '<=', $today)
                                        ->whereDate('end_date', '>=', $today);
                                });
                            } elseif ($filterObj->value == GlobalPatient::FINISHED_TREATMENT_PLAN) {
                                $query->whereHas('treatmentPlans', function (Builder $query) use ($today) {
                                    $query->whereDate('start_date', '<', $today)
                                        ->whereDate('end_date', '<', $today);
                                })->whereDoesntHave('treatmentPlans', function (Builder $query) use ($today) {
                                    $query->whereDate('start_date', '<=', $today)
                                        ->whereDate('end_date', '>=', $today);
                                });
                            }
                        } elseif ($filterObj->columnName === 'gender') {
                            $query->where($filterObj->columnName, $filterObj->value);
                        } elseif ($filterObj->columnName === 'age') {
                            $value = $filterObj->value;
                            $query->where(function ($query) use ($value) {
                                $query->orWhereRaw("YEAR(CURDATE()) - YEAR(date_of_birth) = ?", [$value])
                                    ->orWhereRaw("(YEAR(CURDATE()) = YEAR(date_of_birth) AND MONTH(CURDATE()) - MONTH(date_of_birth) = ?)", [$value])
                                    ->orWhereRaw("(YEAR(CURDATE()) = YEAR(date_of_birth) AND MONTH(CURDATE()) = MONTH(date_of_birth) AND DAY(CURDATE()) - DAY(date_of_birth) = ?)", [$value]);
                            });
                        } elseif ($filterObj->columnName === 'ongoing_treatment_plan') {
                            $query->whereHas('treatmentPlans', function (Builder $query) use ($filterObj) {
                                $query->where('name', 'like', '%' .  $filterObj->value . '%');
                            });
                        } elseif ($filterObj->columnName === 'secondary_therapist') {
                            if ($filterObj->value == GlobalPatient::SECONDARY_TERAPIST) {
                                $query->where(function ($query) use ($therapist_id) {
                                    $query->whereJsonContains('secondary_therapists', intval($therapist_id));
                                });
                            } else {
                                $query->where(function ($query) use ($therapist_id) {
                                    $query->where('secondary_therapists',  'like', '%[]%');
                                });
                            }
                        } else {
                            $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                        }
                    }
                });
            }

            // For global admin.
            if (isset($data['order_by'])) {
                $query->orderBy($data['order_by']);
            }

            $users = $query->paginate($data['page_size'] ?? 60);
            $info = [
                'current_page' => $users->currentPage(),
                'total_count' => $users->total(),
            ];
        }
        return ['success' => true, 'data' => GlobalPatientResource::collection($users), 'info' => $info];
    }

    /**
     * @param integer $patientId
     * @return array
     * @throws \Exception
     */
    public function destroy($patientId)
    {
        $patient = GlobalPatient::where('patient_id', $patientId)->first();
        $country = Country::find($patient->country_id);
        $user = auth()->user();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country->iso_code),
            'country' => $country->iso_code,
        ])->post(env('PATIENT_SERVICE_URL') . '/patient/deleteAccount/' . $patientId, [
            'therapist_id' => $patient->therapist_id,
            'hard_delete' => true,
            'user_id' => $user->id,
            'group' => $user->type,
            'user_name' => $user->last_name . ' ' . $user->first_name,
            'clinic_id' => $user->clinic_id,
            'country_id' => $user->country_id,
        ]);

        if ($response->successful()) {
            $patient->delete();
            return response()->json([
                'success' => true,
                'message' => 'success_message.patient_delete',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        } else {
            return response()->json([
                'success' => false,
                'message' => 'fail_message.patient_delete',
                'status' => $response->status(),
                'error' => $response->json() ?? $response->body(),
            ], $response->status());
        }
    }
}
