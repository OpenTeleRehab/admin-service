<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfessionResource;
use App\Models\Forwarder;
use App\Models\Profession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ProfessionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/profession",
     *     tags={"Profession"},
     *     summary="Lists all profession",
     *     operationId="professionList",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
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
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $countryId = $request->get('country_id');
        $query = Profession::query();

        if (!$countryId && $authUser->country_id) {
            $countryId = $authUser->country_id;
            $query->where('country_id', $countryId);
        }

        if ($authUser->type === User::ADMIN_GROUP_CLINIC_ADMIN) {
            $query->where('type', Profession::TYPE_THERAPIST);
        } else if ($authUser->type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN || $authUser->type === User::GROUP_PHC_WORKER) {
            $query->where('type', Profession::TYPE_PHC_WORKER);
        }

        $professions = $query->get();
        return ['success' => true, 'data' => ProfessionResource::collection($professions)];
    }


    /**
     * @OA\Get(
     *     path="/api/profession",
     *     tags={"Profession"},
     *     summary="Lists all profession",
     *     operationId="professionList",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
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
     */
    public function getList(Request $request)
    {
        $countryId = $request->get('country_id');
        $pageSize = $request->get('page_size', 60);
        $search = $request->get('search', '');

        $query = Profession::query();

         if ($search) {
            $query->where('name' ,'like', '%' . $search . '%');
        }

        if ($request->has('filters')) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'profession_type') {
                        $query->where('type', $filterObj->value);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        if (!$countryId && Auth::user()) {
            $countryId = Auth::user()->country_id;
        }

        $professions = $query->where('professions.country_id', $countryId)->paginate($pageSize);

        return response()->json(['data' => ProfessionResource::collection($professions), 'total' => $professions->total(), 'current_page' => $professions->currentPage()]);
    }

    /**
     * @OA\Post(
     *     path="/api/profession",
     *     tags={"Profession"},
     *     summary="Create profession",
     *     operationId="createProfession",
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Name",
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
        $countryId = Auth::user()->country_id;
        $name = $request->get('name');
        $type = $request->get('type');
        $existedProfession = Profession::where('country_id', $countryId)
            ->where('name', $name)
            ->count();

        if ($existedProfession) {
            return abort(409, 'error_message.profession_exists');
        }

        Profession::create([
            'name' => $name,
            'country_id' => $countryId,
            'type' => $type,
        ]);

        return ['success' => true, 'message' => 'success_message.profession_create'];
    }

    /**
     * @OA\Put(
     *     path="/api/profession/{id}",
     *     tags={"Profession"},
     *     summary="Update profession",
     *     operationId="updateProfession",
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
     *         name="name",
     *         in="query",
     *         description="Name",
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
     * @param \App\Models\Profession $profession
     *
     * @return array|void
     */
    public function update(Request $request, Profession $profession)
    {
        $countryId = Auth::user()->country_id;
        $name = $request->get('name');
        $type = $request->get('type');
        $existedProfession = Profession::where('id', '<>', $profession->id)
            ->where('country_id', $countryId)
            ->where('name', $name)
            ->count();

        if ($existedProfession) {
            return abort(409, 'error_message.profession_exists');
        }

        $profession->update([
            'name' => $name,
            'country_id' => $countryId,
            'type' => $type,
        ]);

        return ['success' => true, 'message' => 'success_message.profession_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/profession/{id}",
     *     tags={"Profession"},
     *     summary="Delete profession",
     *     operationId="deleteProfession",
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
     * @param \App\Models\Profession $profession
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Profession $profession)
    {
        $isUsed = false;
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/therapist/get-used-profession?profession_id=' . $profession->id);

        if (!empty($response) && $response->successful()) {
            $isUsed = $response->json();
        }

        if (!$isUsed) {
            $profession->delete();

            return ['success' => true, 'message' => 'success_message.profession_delete'];
        }

        return ['success' => false, 'message' => 'error_message.profession_delete'];
    }
}
