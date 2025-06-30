<?php

namespace App\Http\Controllers;

use App\Events\ApplyHealthConditionAutoTranslationEvent;
use App\Http\Resources\HealthConditionResource;
use App\Models\HealthCondition;
use App\Models\HealthConditionGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthConditionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health-condition",
     *     tags={"HealthCondition"},
     *     summary="Lists all health conditions",
     *     operationId="healthConditionList",
     *     @OA\Parameter(
     *          name="parent_id",
     *          in="query",
     *          description="Health condition group id",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
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
     *
     * @return array
     */
    public function index(Request $request)
    {
        $query = HealthCondition::query();

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->get('parent_id'));
        }

        $healthConditions = $query->get();

        return [
            'success' => true,
            'data' => HealthConditionResource::collection($healthConditions),
        ];
    }

    /**
     * @param HealthCondition $healthCondition
     *
     * @return HealthConditionResource
     */
    public function show(HealthCondition $healthCondition)
    {
        return new HealthConditionResource($healthCondition);
    }

    /**
     * @OA\Post(
     *     path="/api/health-condition",
     *     tags={"HealthCondition"},
     *     summary="Create health condition",
     *     operationId="createHealthCondition",
     *     @OA\Parameter(
     *         name="health_condition_group",
     *         in="query",
     *         description="Health condition group",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="health_condition_value",
     *         in="query",
     *         description="Health condition name",
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
     * @param Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        if ($request->get('health_condition_group')) {
            $parent = HealthConditionGroup::find($request->get('health_condition_group'));

            $healthConditionTitles = explode(';', $request->get('health_condition_value', ''));
            foreach ($healthConditionTitles as $healthConditionTitle) {
                if (trim($healthConditionTitle)) {
                    $healthCondition = HealthCondition::create([
                        'title' => $healthConditionTitle,
                        'parent_id' => $parent->id,
                    ]);

                    // Add automatic translation for Health Condition.
                    try {
                        event(new ApplyHealthConditionAutoTranslationEvent($healthCondition));
                    } catch (\Exception $e) {
                        Log::warning("Translation failed: " . $e->getMessage());
                    }
                }
            }

            return ['success' => true, 'message' => 'success_message.health_condition_group_add'];
        }

        return ['success' => false, 'message' => 'error_message.health_condition_group_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/health-condition/{id}",
     *     tags={"HealthCondition"},
     *     summary="Update health condition",
     *     operationId="updateHealthCondition",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Health condition id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="health_condition",
     *         in="query",
     *         description="Health condition",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Language id",
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
     * @param Request $request
     * @param HealthCondition $healthCondition
     *
     * @return array
     */
    public function update(Request $request, HealthCondition $healthCondition)
    {
        $healthCondition->update([
            'title' => $request->get('health_condition_value'),
        ]);

        return ['success' => true, 'message' => 'success_message.health_condition_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/health-condition/{id}",
     *     tags={"HealthCondition"},
     *     summary="Delete health condition",
     *     operationId="deleteHealthCondition",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Health condition id",
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
     * @param HealthCondition $healthCondition
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(HealthCondition $healthCondition)
    {
        $healthCondition->delete();

        return ['success' => true, 'message' => 'success_message.health_condition_group_delete'];
    }
}
