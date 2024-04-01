<?php

namespace App\Http\Controllers;

use App\Http\Resources\GlobalPatientResource;
use App\Models\GlobalPatient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
                            ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                            ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
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
                            if ($filterObj->value == GlobalPatient::FINISHED_TREATMENT_PLAN) {
                                $query->whereHas('treatmentPlans', function (Builder $query) {
                                    $query->whereDate('end_date', '<', Carbon::now());
                                })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                    $query->whereDate('end_date', '>', Carbon::now());
                                })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                    $query->whereDate('start_date', '<=', Carbon::now())
                                        ->whereDate('end_date', '>=', Carbon::now());
                                });
                            } elseif ($filterObj->value == GlobalPatient::PLANNED_TREATMENT_PLAN) {
                                $query->whereHas('treatmentPlans', function (Builder $query) {
                                    $query->whereDate('end_date', '>', Carbon::now());
                                })->whereDoesntHave('treatmentPlans', function (Builder $query) {
                                    $query->whereDate('start_date', '<=', Carbon::now())
                                        ->whereDate('end_date', '>=', Carbon::now());
                                });
                            } else {
                                $query->whereHas('treatmentPlans', function (Builder $query) {
                                    $query->whereDate('start_date', '<=', Carbon::now())
                                        ->whereDate('end_date', '>=', Carbon::now());
                                });
                            }
                        } elseif ($filterObj->columnName === 'gender') {
                            $query->where($filterObj->columnName, $filterObj->value);
                        } elseif ($filterObj->columnName === 'age') {
                            $query->whereRaw('YEAR(NOW()) - YEAR(date_of_birth) = ? OR ABS(MONTH(date_of_birth) - MONTH(NOW())) = ?  OR ABS(DAY(date_of_birth) - DAY(NOW())) = ?', [$filterObj->value, $filterObj->value, $filterObj->value]);
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

            $users = $query->paginate($data['page_size']);
            $info = [
                'current_page' => $users->currentPage(),
                'total_count' => $users->total(),
            ];
        }
        return ['success' => true, 'data' => GlobalPatientResource::collection($users), 'info' => $info];
    }
}
