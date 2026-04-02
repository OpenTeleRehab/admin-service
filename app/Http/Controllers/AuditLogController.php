<?php

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\AuditLogResource;
use App\Models\ExtendActivity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Models\Forwarder;

class AuditLogController extends Controller
{
    const KEYCLOAK_EVENT_TYPE_LOGIN = 'access.LOGIN';

    /**
     * @OA\Get(
     *     path="/api/audit-logs",
     *     tags={"AuditLogs"},
     *     summary="Lists all audit logs",
     *     operationId="auditLogList",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $data = $request->all();

        $auditLogs = ExtendActivity::latest('created_at');

        if (!empty($data['search_value'])) {
            $searchValue = $data['search_value'];
            $therapistByName = Http::withToken(
                Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
            )->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-name', ['name' => $searchValue])
            ->json('data', []);

            $therapistIds = collect($therapistByName)->pluck('id')->toArray();
            $auditLogs->where(function ($query) use ($searchValue, $therapistIds) {
                $query->whereHas('user', function ($q) use ($searchValue) {
                    $q->where(function ($q) use ($searchValue) {
                        $q->where('first_name', 'like', '%' . $searchValue . '%')
                        ->orWhere('last_name', 'like', '%' . $searchValue . '%');
                    });
                });
                if (!empty($therapistIds)) {
                    $query->orWhere(function ($q) use ($therapistIds) {
                        $q->where('log_name', ExtendActivity::THERAPIST_SERVICE)
                        ->whereIn('causer_id', $therapistIds);
                    });
                }
            });
        }

        if (!empty($data['filters'])) {
            $filters = $request->get('filters');
            $auditLogs->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);

                    if ($filterObj->columnName === 'type_of_changes') {
                        $query->where('description', $filterObj->value);
                    } elseif ($filterObj->columnName === 'who') {
                        $therapistByName = Http::withToken(
                            Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
                        )->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-name', ['name' => $filterObj->value])
                        ->json('data', []);
                        $therapistIds = collect($therapistByName)->pluck('id')->toArray();

                        $query->where(function ($query) use ($filterObj, $therapistIds) {
                            $query->whereHas('user', function ($q) use ($filterObj) {
                                $q->where(function ($q) use ($filterObj) {
                                    $q->where('first_name', 'like', '%' . $filterObj->value . '%')
                                    ->orWhere('last_name', 'like', '%' . $filterObj->value . '%');
                                });
                            });
                            if (!empty($therapistIds)) {
                                $query->orWhere(function ($q) use ($therapistIds) {
                                    $q->where('log_name', ExtendActivity::THERAPIST_SERVICE)
                                    ->whereIn('causer_id', $therapistIds);
                                });
                            }
                        });
                    } elseif ($filterObj->columnName === 'user_group') {
                        $therapistByGroup = Http::withToken(
                            Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
                        )->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-type', ['type' => $filterObj->value])
                        ->json('data', []);
                        $therapistIds = collect($therapistByGroup)->pluck('id')->toArray();
                        $query->where(function ($subQuery) use ($filterObj, $therapistIds) {
                            $subQuery->whereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->where('type', $filterObj->value);
                            });
                            if (!empty($therapistIds)) {
                                $subQuery->orWhere(function ($q) use ($therapistIds) {
                                    $q->where('log_name', ExtendActivity::THERAPIST_SERVICE)
                                    ->whereIn('causer_id', $therapistIds);
                                });
                            }
                        });
                    } elseif ($filterObj->columnName === 'country') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user.country', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                                ->orWhere('country_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'region') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user.region', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                            ->orWhereHas('user.regions', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                            ->orWhere('region_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'province') {
                        $query->where(function ($q) use ($filterObj) {
                            $q->orWhereHas('user.clinic.province', function ($q) use ($filterObj) {
                                $q->where('id', $filterObj->value);
                            });
                            $q->orWhereHas('user.phcService.province', function ($q) use ($filterObj) {
                                $q->where('id', $filterObj->value);
                            });
                            $q->orWhere('province_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'clinic') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user.clinic', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                                ->orWhere('clinic_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'phc_service') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user.phcService', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                            ->orWhere('phc_service_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'date_time') {
                        $dates = explode(' - ', $filterObj->value);
                        $startDate = date_create_from_format('d/m/Y', $dates[0]);
                        $endDate = date_create_from_format('d/m/Y', $dates[1]);
                        $startDate->format('Y-m-d');
                        $endDate->format('Y-m-d');
                        $query->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate);
                    } elseif ($filterObj->columnName === 'before_changed') {
                        $query->whereRaw("JSON_SEARCH(properties->'$.old', 'one', ?) IS NOT NULL", ["%{$filterObj->value}%"]);
                    } elseif ($filterObj->columnName === 'after_changed') {
                        $query->whereRaw("JSON_SEARCH(properties->'$.attributes', 'one', ?) IS NOT NULL", ["%{$filterObj->value}%"]);
                    } elseif ($filterObj->columnName === 'subject_type') {
                        $query->whereRaw("SUBSTRING_INDEX(subject_type, '\\\\', -1) LIKE ?", ["%{$filterObj->value}%"]);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        if ($user->type === User::ADMIN_GROUP_ORG_ADMIN) {
            $auditLogs->where(function ($subQuery) {
                $subQuery->orWhereHas('user', function ($subQuery) {
                    $subQuery->where('type', '<>', User::ADMIN_GROUP_SUPER_ADMIN);
                })
                ->orWhereNotNull('country_id');
            });
        } else if ($user->type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user.country', function ($subQuery) use ($user) {
                    $subQuery->where('id', $user->country_id);
                })
                ->orWhere('country_id', $user->country_id);
            });
        } elseif ($user->type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
            $userRegionIds = $user->regions->pluck('id');
            $auditLogs->where(function ($subQuery) use ($userRegionIds) {
                $subQuery->orWhereHas('user', function ($query) use ($userRegionIds) {
                    $query->whereHas('regions', fn($q) => $q->whereIn('id', $userRegionIds))
                    ->orWhereHas('region', fn($q) => $q->whereIn('id', $userRegionIds));
                })
                ->orWhereIn('region_id', $userRegionIds);
            });
        } elseif ($user->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user.clinic', function ($subQuery) use ($user) {
                    $subQuery->where('id', $user->clinic_id);
                })
                    ->orWhere('clinic_id', $user->clinic_id);
            });
        } elseif ($user->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user.phcService', function ($subQuery) use ($user) {
                    $subQuery->where('id', $user->phc_service_id);
                })
                    ->orWhere('phc_service_id', $user->phc_service_id);
            });
        }

        $auditLogs = $auditLogs->paginate($data['page_size'] ?? 10);
        $info = [
            'current_page' => $auditLogs->currentPage(),
            'total_count' => $auditLogs->total()
        ];

        $therapistIds = $auditLogs
            ->whereIn('log_name', [ExtendActivity::THERAPIST_SERVICE, ExtendActivity::PATIENT_SERVICE])
            ->pluck('causer_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $patientIds = $auditLogs
            ->where('log_name', ExtendActivity::PATIENT_SERVICE)
            ->pluck('causer_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $therapistResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-ids', ['ids' => $therapistIds])
            ->json('data', []);
        $patientResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE))
            ->get(env('PATIENT_SERVICE_URL') . '/patient/list/by-ids', ['patient_ids' => $patientIds])
            ->json('data', []);
        $therapists = collect($therapistResponse)->keyBy('id');
        $patients = collect($patientResponse)->keyBy('id');
        $auditLogCollection = collect($auditLogs->items());
        $mappedAuditLogs = collect($auditLogCollection)->map(function ($log) use ($therapists, $patients, $user) {
            if ($log->group && $log->full_name) {
                $log->causer_group = $log->group;
                $log->causer_name = $log->full_name;
            } elseif ($log->log_name === ExtendActivity::THERAPIST_SERVICE) {
                $therapist  = $therapists[$log->causer_id] ?? null;
                $log->causer_name = UserHelper::getFullName($therapist['last_name'] ?? null, $therapist['first_name'] ?? null, $user->langauge_id) ?? null;
                $log->causer_group = $therapist['type'] ?? null;
            } elseif ($log->log_name === ExtendActivity::PATIENT_SERVICE) {
                $patient = $patients[$log->causer_id] ?? null;
                $log->causer_name = $patient['identity'] ?? null;
                $log->causer_group = User::GROUP_PATIENT;
            }

            return $log;
        });

        return ['success' => true, 'data' => AuditLogResource::collection($mappedAuditLogs), 'info' => $info];
    }

    /**
     * @OA\Post(
     *     path="/api/audit-logs",
     *     tags={"AuditLogs"},
     *     summary="Store audit logs of user",
     *     operationId="createAuditLog",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="authDetails",
     *                 type="object",
     *                 @OA\Property(property="username", type="string", example="user@example.com")
     *             ),
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 description="Action of log such as 'create', 'update', or 'login'",
     *                 example="login"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     security={{"oauth2_security": {}}}
     * )
     */
    public function store(Request $request)
    {
        $auth = $request->get('authDetails');
        $details = $request->get('details');
        $user = User::where('email', $auth['username'])->first();
        $type = $request->get('type');
        $storeData = [
            'attributes' => ['user_id' => $user->id]
        ];
        // Check if user just refresh the page
        $isRefresh = isset($details['response_mode'], $details['response_type']) && !isset($details['custom_required_action']);
        if (!$isRefresh && $user && $user->email !== env('KEYCLOAK_BACKEND_USERNAME') && $user->email !== env('KEYCLOAK_LIBRARY_USERNAME')) {
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties($storeData)
                ->useLog('admin_service')
                ->log($type === self::KEYCLOAK_EVENT_TYPE_LOGIN ? 'login' : 'logout');
        }

        return ['success' => true];
    }
}
