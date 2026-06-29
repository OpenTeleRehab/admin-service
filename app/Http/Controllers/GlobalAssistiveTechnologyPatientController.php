<?php

namespace App\Http\Controllers;

use App\Http\Resources\GlobalAssistiveTechnologyPatientResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\GlobalAssistiveTechnologyPatient;

class GlobalAssistiveTechnologyPatientController extends Controller
{
    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $authUser = Auth::user();

        $query = GlobalAssistiveTechnologyPatient::selectRaw("
                patient_id,
                ANY_VALUE(gender) as gender,
                ANY_VALUE(identity) as identity,
                ANY_VALUE(date_of_birth) as date_of_birth,
                ANY_VALUE(provision_date) as provision_date,
                ANY_VALUE(country_id) as country_id,
                ANY_VALUE(clinic_id) as clinic_id,
                GROUP_CONCAT(assistive_technology_id) as assistive_technology_id
            ")
            ->whereNull('deleted_at');

        switch ($authUser->type) {
            case User::ADMIN_GROUP_COUNTRY_ADMIN:
                $query->where('country_id', $authUser->country_id);
                break;
            case User::ADMIN_GROUP_REGIONAL_ADMIN:
                $userRegionIds = $authUser->regions->pluck('id')->toArray();
                $query->where(function ($subQuery) use ($userRegionIds) {
                    $subQuery->whereHas('clinic', fn($q) =>
                        $q->whereIn('region_id', $userRegionIds)
                    )
                    ->orWhereHas('phcService.province', fn($q) =>
                        $q->whereIn('region_id', $userRegionIds)
                    );
                });
                break;
            case User::ADMIN_GROUP_CLINIC_ADMIN:
                $query->where('clinic_id', $authUser->clinic_id);
                break;
            case User::ADMIN_GROUP_PHC_SERVICE_ADMIN:
                $query->where('phc_service_id', $authUser->phc_service_id);
                break;
            default:
                $query;
        }

        if (isset($data['from_date']) && isset($data['to_date'])) {
            $fromDate = date_create_from_format('d/m/Y', $data['from_date']);
            $toDate = date_create_from_format('d/m/Y', $data['to_date']);
            $query->whereBetween('provision_date', [date_format($fromDate, config('settings.defaultTimestampFormat')), date_format($toDate, config('settings.defaultTimestampFormat'))]);
        }

        if (isset($data['search_value'])) {
            $query->where(function ($query) use ($data) {
                $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('gender', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('name', 'like', '%' . $data['search_value'] . '%');
            });
        }

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'provision_date') {
                        $dates = explode(' - ', $filterObj->value);
                        $startDate = date_create_from_format('d/m/Y', $dates[0]);
                        $endDate = date_create_from_format('d/m/Y', $dates[1]);
                        $startDate->format('Y-m-d');
                        $endDate->format('Y-m-d');
                        $query->whereDate($filterObj->columnName, '>=', $startDate)
                            ->whereDate($filterObj->columnName, '<=', $endDate);
                    } elseif ($filterObj->columnName === 'age') {
                        $query->whereRaw('YEAR(NOW()) - YEAR(date_of_birth) = ? OR ABS(MONTH(date_of_birth) - MONTH(NOW())) = ?  OR ABS(DAY(date_of_birth) - DAY(NOW())) = ?', [$filterObj->value, $filterObj->value, $filterObj->value]);
                    } elseif ($filterObj->columnName === 'gender') {
                        $query->where($filterObj->columnName, $filterObj->value);
                    } elseif ($filterObj->columnName === 'assistive_technology') {
                        $query->whereIn('assistive_technology_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'country') {
                        $query->where('country_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'clinic') {
                        $query->where('clinic_id', $filterObj->value);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        if (isset($data['order_by'])) {
            $query->orderBy($data['order_by']);
        }

        $users = $query->groupBy('patient_id')->paginate($data['page_size']);
        $info = [
            'current_page' => $users->currentPage(),
            'total_count' => $users->total(),
        ];

        return ['success' => true, 'data' => GlobalAssistiveTechnologyPatientResource::collection($users), 'info' => $info];
    }
}
