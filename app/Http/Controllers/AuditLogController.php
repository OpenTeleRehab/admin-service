<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\AuditLogResource;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/audit-logs",
     *     tags={"AditLogs"},
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

    public function store(Request $request)
    {
        $user = Auth::user();
        $type = $request->get('type');
        $storeData = [
            'attributes' => ['user_id' => $user->id]
        ];

        activity()
           ->performedOn($user)
           ->causedBy($user)
           ->withProperties($storeData)
           ->useLog('Auth')
           ->log($type);
        return ['success' => true];
    }
}
