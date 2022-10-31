<?php

namespace App\Http\Controllers;

use App\Http\Resources\InternationalClassificationDiseaseResource;
use App\Models\Forwarder;
use App\Models\InternationalClassificationDisease;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class InternationalClassificationDiseaseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/disease",
     *     tags={"International Classification Disease"},
     *     summary="Lists all diseases",
     *     operationId="diseaseList",
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
        $internationalClassificationDiseases = InternationalClassificationDisease::all();
        return ['success' => true, 'data' => InternationalClassificationDiseaseResource::collection($internationalClassificationDiseases)];
    }

    /**
     * @OA\Post(
     *     path="/api/disease",
     *     tags={"International Classification Disease"},
     *     summary="Create disease",
     *     operationId="createDisease",
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Language id (English is the default language when create)",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             enum={1}
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
        $name = $request->get('name');
        $lang = Language::find($request->get('lang'));
        $existedDisease = InternationalClassificationDisease::where('name->'.strtolower($lang->code), '=', $name)->count();

        if ($existedDisease) {
            return abort(409, 'error_message.disease_exists');
        }

        InternationalClassificationDisease::create([
            'name' => $name
        ]);

        return ['success' => true, 'message' => 'success_message.disease_create'];
    }

    /**
     * @param \App\Models\InternationalClassificationDisease $disease
     *
     * @return \App\Http\Resources\InternationalClassificationDiseaseResource
     */
    public function show(InternationalClassificationDisease $disease)
    {
        return new InternationalClassificationDiseaseResource($disease);
    }

    /**
     * @OA\Put(
     *     path="/api/disease/{id}",
     *     tags={"International Classification Disease"},
     *     summary="Update disease",
     *     operationId="UpdateDisease",
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
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Language id",
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
     * @param \App\Models\InternationalClassificationDisease $disease
     *
     * @return array|void
     */
    public function update(Request $request, InternationalClassificationDisease $disease)
    {
        $name = $request->get('name');
        $lang = Language::find($request->get('lang'));
        $existedDisease = InternationalClassificationDisease::where('id', '<>', $disease->id)
            ->where('name->'.strtolower($lang->code), '=', $name)
            ->count();

        if ($existedDisease) {
            return abort(409, 'error_message.disease_exists');
        }

        $disease->update([
            'name' => $name,
        ]);

        return ['success' => true, 'message' => 'success_message.disease_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/disease/{id}",
     *     tags={"International Classification Disease"},
     *     summary="Delete disease",
     *     operationId="deleteDisease",
     *      @OA\Parameter(
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
     * @param \App\Models\InternationalClassificationDisease $disease
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(InternationalClassificationDisease $disease)
    {
        $isUsed = false;
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE))
            ->get(env('PATIENT_SERVICE_URL') . '/treatment-plan/get-used-disease?disease_id=' . $disease->id);

        if (!empty($response) && $response->successful()) {
            $isUsed = $response->json();
        }

        if (!$isUsed) {
            $disease->delete();

            return ['success' => true, 'message' => 'success_message.disease_delete'];
        }

        return ['success' => false, 'message' => 'error_message.disease_delete'];
    }

    /**
     * @OA\Get(
     *     path="/api/disease/get-name/by-id",
     *     tags={"International Classification Disease"},
     *     summary="Disease name",
     *     operationId="getDiseaseNameById",
     *      @OA\Parameter(
     *         name="disease_id",
     *         in="query",
     *         description="Disease id",
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
     * @param Request $request
     * @return int
     */
    public function getDiseaseNameById(Request $request)
    {
        $id = $request->get('disease_id');
        $disease = InternationalClassificationDisease::where('id', $id)->first();

        return $disease ? $disease->name : '';
    }
}
