<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\AuditLogResource;
use App\Models\ExtendActivity;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class AuditLogController extends Controller
{
    const COUNTRY_ADMIN = 'country_admin';
    const CLINIC_ADMIN = 'clinic_admin';
    const SUPER_ADMIN = 'super_admin';
    const ORGANIZATION_ADMIN = 'organization_admin';
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
        if (!in_array($user->type, [self::SUPER_ADMIN, self::COUNTRY_ADMIN, self::CLINIC_ADMIN, self::ORGANIZATION_ADMIN])) {
            return response()->json([
                'success' => false,
                'message' => 'error_message.permission'
            ], 403);
        }

        $auditLogs = ExtendActivity::latest('created_at');

        if (!empty($data['search_value'])) {
            $searchValue = $data['search_value'];
            $auditLogs->where(function ($query) use ($searchValue) {
                $columns = Schema::getColumnListing('activity_log');

                foreach ($columns as $column) {
                    $query->orWhere($column, 'LIKE', "{$searchValue}%");
                }

                $query->orWhereHas('user', function ($subQuery) use ($searchValue) {
                    $subQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["{$searchValue}%"]);
                });

                $query->orWhereHas('user.clinic', function ($subQuery) use ($searchValue) {
                    $subQuery->where('name', 'LIKE', "{$searchValue}%");
                });

                $query->orWhereHas('user.country', function ($subQuery) use ($searchValue) {
                    $subQuery->where('name', 'LIKE', "{$searchValue}%");
                });
            });
        }

        if (!empty($data['filters'])) {
            $filters = $request->get('filters');
            $auditLogs->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);

                    if ($filterObj->columnName === 'type_of_changes') {
                        $query->where('description', 'LIKE', "%{$filterObj->value}%");
                    } elseif ($filterObj->columnName === 'who') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->whereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->whereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$filterObj->value}%"]);
                            })
                                ->orWhere('full_name', 'LIKE', "%{$filterObj->value}%");
                        });
                    } elseif ($filterObj->columnName === 'user_group') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->whereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->where('type', $filterObj->value);
                            })
                                ->orWhere('group', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'clinic') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user.clinic', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                                ->orWhere('clinic_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'country') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user.country', function ($subQuery) use ($filterObj) {
                                $subQuery->where('id', $filterObj->value);
                            })
                                ->orWhere('country_id', $filterObj->value);
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
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        if ($user->type === self::ORGANIZATION_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user', function ($subQuery) use ($user) {
                    $subQuery->where('type', '<>', self::SUPER_ADMIN);
                })
                    ->orWhere('group', '<>', self::SUPER_ADMIN);
            });
        } else if ($user->type === self::COUNTRY_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user.country', function ($subQuery) use ($user) {
                    $subQuery->where('id', $user->country_id);
                })
                    ->orWhere('country_id', $user->country_id);
            });
        } elseif ($user->type === self::CLINIC_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user.clinic', function ($subQuery) use ($user) {
                    $subQuery->where('id', $user->clinic_id);
                })
                    ->orWhere('clinic_id', $user->clinic_id);
            });
        }

        $auditLogs = $auditLogs->paginate($data['page_size'] ?? 10);
        $info = [
            'current_page' => $auditLogs->currentPage(),
            'total_count' => $auditLogs->total()
        ];
        return ['success' => true, 'data' => AuditLogResource::collection($auditLogs), 'info' => $info];
    }

    /**
     * @OA\Post(
     *     path="/api/audit-logs",
     *     tags={"AuditLogs"},
     *     summary="Store audit logs of user",
     *     operationId="createAuditLog",
     *     @OA\Parameter(
     *         name="authDetails",
     *         in="query",
     *         description="auth details from Keycloak",
     *         required=true,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="array",
     *                 @OA\Property(property="username", type="string"),
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Action of log such as 'create', 'update', or 'login'",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
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
        if (!$isRefresh && $user && $user->email !== env('KEYCLOAK_BACKEND_USERNAME')) {
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
