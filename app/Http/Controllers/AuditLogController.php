<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AuditLogResource;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
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
        $pageSize = $request->get('page_size');
        $auditLogs = Activity::paginate($pageSize);
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
     *     summary="Store audit logs for admin_service, therapist_service, and patient_service",
     *     operationId="createAuditLog",
     *     @OA\Parameter(
      *         name="log_name",
      *         in="query",
      *         description="Source of log. It could be admin_service, therapist_service, or patient_service",
      *         required=true,
      *         @OA\Schema(
      *             type="string"
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
      *     @OA\Parameter(
      *         name="user_id",
      *         in="query",
      *         description="User id is required if the log is not from admin_service",
      *         required=false,
      *         @OA\Schema(
      *             type="integer"
      *         )
      *     ),
      *     @OA\Parameter(
      *         name="user_id",
      *         in="query",
      *         description="User id is required if the log is not from admin_service",
      *         required=false,
      *         @OA\Schema(
      *             type="string"
      *         )
      *     ),
      *     @OA\Parameter(
      *         name="user_full_name",
      *         in="query",
      *         description="User full name is required if the log is not from admin_service",
      *         required=false,
      *         @OA\Schema(
      *             type="string"
      *         )
      *     ),
      *     @OA\Parameter(
      *         name="user_groups",
      *         in="query",
      *         description="User groups is required if the log is not from admin_service",
      *         required=false,
      *         @OA\Schema(
      *             type="integer"
      *         )
      *     ),
     *     @OA\Parameter(
     *         name="properties",
     *         in="query",
     *         description="Detail changes for both before and after changes and is required if the log is not from admin_service",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
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
        $logName = $request->get('log_name') ?? '';
        if (empty($logName)) {
            return [ 'success' => false ];
        }

        $type = $request->get('type');
        $storeData = [];
        if ($logName !== 'admin_service') {
            $rules = [
                'user_id' => 'required',
                'user_full_name' => 'required',
                'user_email' => 'required',
                'user_groups' => 'required',
                'properties' => 'required'
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return [ 'success' => false ];
            }

            $storeData = array_merge(
                [
                    'meta' => [
                        'user_id' => $request->get('user_id'),
                        'user_email' => $request->get('user_email'),
                        'user_full_name' => $request->get('user_full_name'),
                        'user_groups' => $request->get('user_groups'),
                    ]
                ],
                $request->get('properties')
            );
            activity()
               ->withProperties(['customProperty' => $storeData])
               ->useLog($logName)
               ->log($type);
        } else {
            $user = Auth::user();
            $storeData = [
                'attributes' => ['user_id' => $user->id]
            ];
            activity()
               ->performedOn($user)
               ->causedBy($user)
               ->withProperties($storeData)
               ->useLog($logName)
               ->log($type);
        }

        return [ 'success' => true ];
    }
}
