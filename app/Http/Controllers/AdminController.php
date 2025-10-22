<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Models\EducationMaterial;
use App\Models\Exercise;
use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin",
     *     tags={"Admin"},
     *     summary="Lists all users",
     *     operationId="userList",
     *     @OA\Parameter(
     *         name="search_value",
     *         in="query",
     *         description="Search value",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             default=" "
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="admin_type",
     *         in="query",
     *         description="Admin type",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         required=true,
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
    public function index(Request $request)
    {
        $data = $request->all();
        $query = User::select('users.*')
            ->leftJoin('countries', 'countries.id', 'users.country_id')
            ->leftJoin('clinics', 'clinics.id', 'users.clinic_id')
            ->where('type', $data['admin_type'])
            ->where(function ($query) use ($data) {
                $query->where('first_name', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('email', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('countries.name', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('clinics.name', 'like', '%' . $data['search_value'] . '%');
            });

        if (Auth::user()->country_id) {
            $countryId = Auth::user()->country_id;
            $query->where('users.country_id', $countryId);
        }

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'status') {
                        $query->where('enabled', $filterObj->value);
                    } elseif ($filterObj->columnName === 'country') {
                        $query->where('countries.id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'clinic') {
                        $query->where('clinics.id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'last_login') {
                        $dates = explode(' - ', $filterObj->value);
                        $startDate = date_create_from_format('d/m/Y', $dates[0]);
                        $endDate = date_create_from_format('d/m/Y', $dates[1]);
                        $startDate->format('Y-m-d');
                        $endDate->format('Y-m-d');
                        $query->whereDate('last_login', '>=', $startDate)
                            ->whereDate('last_login', '<=', $endDate);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' . $filterObj->value . '%');
                    }
                }
            });
        }

        $users = $query->paginate($data['page_size']);
        $info = [
            'current_page' => $users->currentPage(),
            'total_count' => $users->total(),
        ];
        return ['success' => true, 'data' => UserResource::collection($users), 'info' => $info];
    }

    /**
     * @OA\Post(
     *     path="/api/admin",
     *     tags={"Admin"},
     *     summary="Create user",
     *     operationId="createUser",
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="Email",
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
     * @return array|void
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $type = $request->get('type');
        $countryId = $request->get('country_id');
        $clinicId = $request->get('clinic_id');
        $email = $request->get('email');

        $availableEmail = User::where('email', $email)->count();
        if ($availableEmail) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.email_exists');
        }

        $user = User::create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'type' => $type,
            'country_id' => $countryId,
            'clinic_id' => $clinicId
        ]);

        if (!$user) {
            return ['success' => false, 'message' => 'error_message.user_add'];
        }

        try {
            KeycloakHelper::createUser($user, null, false, $type);
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        DB::commit();
        return ['success' => true, 'message' => 'success_message.user_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/admin/{id}",
     *     tags={"Admin"},
     *     summary="Update user",
     *     operationId="updateUser",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
     *         required=true,
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
     * @param int $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $data = $request->all();
            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'clinic_id' => $data['clinic_id'],
                'country_id' => $data['country_id'],
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }

    /**
     * @OA\Post(
     *     path="/api/admin/updateStatus/{id}",
     *     tags={"Admin"},
     *     summary="Update user status",
     *     operationId="updateUserStatus",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="enabled",
     *         in="query",
     *         description="Enable",
     *         required=true,
     *         @OA\Schema(
     *             type="boolean"
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
     * @param Request $request
     * @param \App\Models\User $user
     * @return array
     */
    public function updateStatus(Request $request, User $user)
    {
        try {
            $enabled = $request->boolean('enabled');
            $token = KeycloakHelper::getKeycloakAccessToken();
            $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $user->email;
            $user->update(['enabled' => $enabled]);

            $response = Http::withToken($token)->get($userUrl);
            $keyCloakUsers = $response->json();
            $url = KeycloakHelper::getUserUrl() . '/' . $keyCloakUsers[0]['id'];

            $userUpdated = Http::withToken($token)
                ->put($url, ['enabled' => $enabled]);

            if ($userUpdated) {
                return ['success' => true, 'message' => 'success_message.user_update'];
            }
            return ['success' => false, 'message' => 'error_message.user_update'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/{id}",
     *     tags={"Admin"},
     *     summary="Delete user",
     *     operationId="DeleteUser",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
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
     * @param integer $id
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $token = KeycloakHelper::getKeycloakAccessToken();

            $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $user->email;
            $response = Http::withToken($token)->get($userUrl);

            if ($response->successful()) {
                $keyCloakUsers = $response->json();

                $isDeleted = KeycloakHelper::deleteUser($token, KeycloakHelper::getUserUrl() . '/' . $keyCloakUsers[0]['id']);
                if ($isDeleted) {
                    $user->delete();
                    return ['success' => true, 'message' => 'success_message.user_delete'];
                }
            }
            return ['success' => false, 'message' => 'error_message.user_delete'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param string $token
     * @param string $userUrl
     * @param string $userGroup
     * @param false $isUnassigned
     *
     * @return bool
     */
    private static function assignUserToGroup($token, $userUrl, $userGroup, $isUnassigned = false)
    {
        $userGroups = KeycloakHelper::getUserGroups($token);
        $url = $userUrl . '/groups/' . $userGroups[$userGroup];
        if ($isUnassigned) {
            $response = Http::withToken($token)->delete($url);
        } else {
            $response = Http::withToken($token)->put($url);
        }
        if ($response->successful()) {
            return true;
        }
        return false;
    }

    /**
     * @param string $userId
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendEmailToNewUser($userId)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        $url = KeycloakHelper::getUserUrl() . '/'. $userId . KeycloakHelper::getExecuteEmailUrl();
        $response = Http::withToken($token)->put($url, ['UPDATE_PASSWORD']);

        return $response;
    }

    /**
     * @param User $user
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function resendEmailToUser(User $user)
    {
        $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));
        $email = strtolower($user->email);

        if (Str::endsWith($email, $federatedDomains)) {
            return ['success' => false, 'message' => 'error_message.cannot_resend_email'];
        }

        $token = KeycloakHelper::getKeycloakAccessToken();

        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->get(KeycloakHelper::getUserUrl(), [
            'username' => $user->email,
        ]);

        if ($response->successful()) {
            $userUid = $response->json()[0]['id'];
            $isCanSend = self::sendEmailToNewUser($userUid);

            if ($isCanSend) {
                return ['success' => true, 'message' => 'success_message.resend_email'];
            }
        }

        return ['success' => false, 'message' => 'error_message.cannot_resend_email'];
    }

    /**
     * @OA\Post (
     *     path="/api/library/delete/by-therapist",
     *     tags={"Exercise"},
     *     summary="Library delete by therapist",
     *     operationId="deleteLibraryByTherapist",
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="hard_delete",
     *         in="query",
     *         description="Hard delete",
     *         required=true,
     *         @OA\Schema(
     *             type="boolean"
     *         )
     *      ),
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
     * @param Request $request
     * @return array
     */
    public function deleteLibraryByTherapist(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $hardDelete = $request->get('hard_delete');

        if ($hardDelete) {
            Exercise::where('therapist_id', $therapistId)->forceDelete();
            EducationMaterial::where('therapist_id', $therapistId)->forceDelete();
            Questionnaire::where('therapist_id', $therapistId)->forceDelete();
        } else {
            Exercise::where('therapist_id', $therapistId)->delete();
            EducationMaterial::where('therapist_id', $therapistId)->delete();
            Questionnaire::where('therapist_id', $therapistId)->delete();
        }
    }
}
