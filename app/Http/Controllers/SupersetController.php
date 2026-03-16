<?php

namespace App\Http\Controllers;

use App\Helpers\SupersetHelper;
use App\Models\Configuration;
use Illuminate\Support\Facades\Auth;

class SupersetController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $config = Configuration::where('name', Configuration::SUPERSET_CONFIG)->first();

        if (!$config) {
            abort(500, 'Superset configuration not found. Please set it up first.');
        }

        $roleConfig = collect($config->config)->firstWhere('role', $user->type);

        $replacementsMap = [
            '{{country_id}}' => $user->country_id ?? null,
            '{{region_ids}}' => $user->regions?->pluck('id')->join(', '),
            '{{clinic_id}}' => $user->clinic_id ?? null,
            '{{phc_service_id}}' => $user->phc_service_id ?? null,
            '{{user_role}}' => $user->type ?? null,
            '{{therapist_user_id}}' => $user->therapist_user_id ?? null,
            '{{auth_id}}' => $user->id,
        ];

        $guestTokenPayload = [
            'user' => [
                'username' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ],
            'resources' => [
                ['type' => 'dashboard', 'id' => $roleConfig['dashboard_id']]
            ],
            'rls' => SupersetHelper::buildRlsClauses($replacementsMap, $roleConfig['rls'])
        ];

        $guestToken = SupersetHelper::generateGuestToken($guestTokenPayload);

        $expirationTime = SupersetHelper::getExpirationTime($guestToken);

        return response()->json([
            'success' => true,
            'data' => [
                'dashboard_id' => $roleConfig['dashboard_id'],
                'guest_token' => $guestToken,
                'expiration_time' => $expirationTime
            ],
        ]);
    }
}
