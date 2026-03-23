<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Helpers\KeycloakHelper;
use Illuminate\Support\Facades\Http;

class UserMfaController extends Controller
{
    public function resetOtp(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
        ]);

        $userType = $validated['type'];
        $email = null;

        try {
            if (in_array($userType, [User::GROUP_THERAPIST, User::GROUP_PHC_WORKER])) {
                $accessToken = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
                $endpoint = '/therapist/by-id';
                $method = 'get';

                $response = Http::withToken($accessToken)->{$method}(
                    env('THERAPIST_SERVICE_URL') . $endpoint,
                    ['id' => $id]
                );

                if (!$response->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'mfa.reset.user_not_found',
                    ], 404);
                }

                $email = $response->json('email');

                if (empty($email)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'mfa.reset.user_not_found',
                    ], 404);
                }
            } else {
                $user = User::find($id);

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'mfa.reset.user_not_found',
                    ], 404);
                }

                $email = $user->email;
            }

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'mfa.reset.email_not_found',
                ], 404);
            }

            $success = KeycloakHelper::deleteUserCredentialByTypeByUserType($email, 'otp', $userType);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'mfa.reset.failed',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'mfa.reset.success',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'mfa.reset.failed',
            ], 500);
        }
    }
}
