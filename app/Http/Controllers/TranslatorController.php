<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TranslatorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/translator",
     *     tags={"Translator"},
     *     summary="Lists all translators",
     *     operationId="translatorList",
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
        $query = User::with(['translatorLanguages:id,name'])
            ->select('users.*')
            ->where('type', User::GROUP_TRANSLATOR);

        if (isset($data['search_value'])) {
            $query->where(function ($query) use ($data) {
                $query->where('first_name', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
                    ->orWhere('email', 'like', '%' . $data['search_value'] . '%');
            });
        }

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'status') {
                        $query->where('enabled', $filterObj->value);
                    } elseif ($filterObj->columnName === 'last_login') {
                        $dates = explode(' - ', $filterObj->value);
                        $startDate = date_create_from_format('d/m/Y', $dates[0]);
                        $endDate = date_create_from_format('d/m/Y', $dates[1]);
                        $startDate->format('Y-m-d');
                        $endDate->format('Y-m-d');
                        $query->whereDate('last_login', '>=', $startDate)
                            ->whereDate('last_login', '<=', $endDate);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        $query->orderBy('id', 'desc');

        $users = $query->paginate($data['page_size'] ?? 10);
        $info = [
            'current_page' => $users->currentPage(),
            'total_count' => $users->total(),
        ];
        return response()->json(['success' => true, 'data' => UserResource::collection($users), 'info' => $info]);
    }

    /**
     * @OA\Post(
     *     path="/api/translator",
     *     tags={"Translator"},
     *     summary="Create translator",
     *     operationId="createTranslator",
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
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'edit_language_ids' => 'required|array|min:1',
            'edit_language_ids.*' => 'required|integer|exists:languages,id',
        ], [
            'email.unique' => 'error_message.email_exists',
        ]);

        DB::transaction(function () use ($validatedData) {
            $type = User::GROUP_TRANSLATOR;
            $user = User::create([...$validatedData, 'type' => $type]);

            $user->translatorLanguages()->attach($validatedData['edit_language_ids']);

            self::createKeycloakUser($user, $type);
        });

        return response()->json(['success' => true, 'message' => 'success_message.translator_add'], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/translator/{id}",
     *     tags={"Translator"},
     *     summary="Update translator",
     *     operationId="updateTranslator",
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
        $validatedData = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'edit_language_ids' => 'required|array|min:1',
            'edit_language_ids.*' => 'required|integer|exists:languages,id',
        ]);

        DB::transaction(function () use ($id, $validatedData) {
            $user = User::findOrFail($id);
            $user->update($validatedData);

            $user->translatorLanguages()->sync($validatedData['edit_language_ids']);
        });

        return response()->json(['success' => true, 'message' => 'success_message.translator_update']);
    }

    /**
     * @OA\Post(
     *     path="/api/translator/updateStatus/{id}",
     *     tags={"Admin"},
     *     summary="Update translator status",
     *     operationId="updateTranslatorStatus",
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

            $response = Http::withToken($token)->get($userUrl);

            if (!$response->successful()) {
                throw new HttpException($response->status(), $response->body());
            }

            $keyCloakUsers = $response->json();
            $url = KeycloakHelper::getUserUrl() . '/' . $keyCloakUsers[0]['id'];

            $userUpdated = Http::withToken($token)
                ->put($url, ['enabled' => $enabled]);

            if ($userUpdated) {
                $user->update(['enabled' => $enabled]);

                return ['success' => true, 'message' => 'success_message.translator_update'];
            }

            return response()->json(['success' => false, 'message' => 'error_message.translator_update'], 502);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/translator/{id}",
     *     tags={"Translator"},
     *     summary="Delete translator",
     *     operationId="DeleteTranslator",
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
                    return ['success' => true, 'message' => 'success_message.translator_delete'];
                }
            }
            return ['success' => false, 'message' => 'error_message.translator_delete'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param \App\Models\User $user
     * @param string $userGroup
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    private function createKeycloakUser($user, $userGroup)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        if ($token) {
            try {
                $language = Language::find(Auth::user()->language_id);
                $languageCode = $language ? $language->code : '';
                $response = Http::withToken($token)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post(KeycloakHelper::getUserUrl(), [
                    'username' => $user->email,
                    'email' => $user->email,
                    'enabled' => true,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'attributes' => [
                        'locale' => [$languageCode]
                    ]
                ]);

                if ($response->successful()) {
                    $createdUserUrl = $response->header('Location');
                    $lintArray = explode('/', $createdUserUrl);
                    $userKeycloakUuid = end($lintArray);
                    $isCanSetPassword = true;
                    $isCanAssignUserToGroup = self::assignUserToGroup($token, $createdUserUrl, $userGroup);
                    if ($isCanSetPassword && $isCanAssignUserToGroup) {
                        self::sendEmailToNewUser($userKeycloakUuid);
                        return $userKeycloakUuid;
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
        throw new \Exception('no_token');
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
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function resendEmailToUser(User $user)
    {
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
}
