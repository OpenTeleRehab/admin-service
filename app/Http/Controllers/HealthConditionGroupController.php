<?php

namespace App\Http\Controllers;

use App\Events\ApplyHealthConditionAutoTranslationEvent;
use App\Events\ApplyHealthConditionGroupAutoTranslationEvent;
use App\Http\Resources\HealthConditionGroupResource;
use App\Models\HealthCondition;
use App\Models\HealthConditionGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthConditionGroupController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health-condition-group",
     *     tags={"HealthConditionGroup"},
     *     summary="Lists all health condition groups",
     *     operationId="healthConditionGroupList",
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
     * @return array
     */
    public function index()
    {
        $healthConditionGroups = HealthConditionGroup::all();

        return [
            'success' => true,
            'data' => HealthConditionGroupResource::collection($healthConditionGroups),
        ];
    }

    /**
     * @param HealthConditionGroup $healthConditionGroup
     *
     * @return HealthConditionGroupResource
     */
    public function show(HealthConditionGroup $healthConditionGroup)
    {
        return new HealthConditionGroupResource($healthConditionGroup);
    }

    /**
     * @OA\Post(
     *     path="/api/health-condition-group",
     *     tags={"HealthConditionGroup"},
     *     summary="Create health condition group",
     *     operationId="createHealthConditionGroup",
     *     @OA\Parameter(
     *          name="current_health_condition_group",
     *          in="query",
     *          description="Parent health condition group id",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\Parameter(
     *         name="health_condition_group",
     *         in="query",
     *         description="Health condition group name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
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
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        if ($request->get('current_health_condition_group')) {
            $parent = HealthConditionGroup::find($request->get('current_health_condition_group'));
        } else {
            $parent = HealthConditionGroup::create([
                'title' => $request->get('health_condition_group'),
            ]);

            // Add automatic translation for Health Condition Group.
            try {
                event(new ApplyHealthConditionGroupAutoTranslationEvent($parent));
            } catch (\Exception $e) {
                Log::warning("Translation failed: " . $e->getMessage());
            }
        }

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

    /**
     * @OA\Put(
     *     path="/api/health-condition-group/{id}",
     *     tags={"HealthConditionGroup"},
     *     summary="Update health condition group",
     *     operationId="updateHealthConditionGroup",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Health condition group id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="health_condition_group",
     *         in="query",
     *         description="Health condition name",
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
     * @param HealthConditionGroup $healthConditionGroup
     *
     * @return array
     */
    public function update(Request $request, HealthConditionGroup $healthConditionGroup)
    {
        $healthConditionGroup->update([
            'title' => $request->get('health_condition_group'),
        ]);

        return ['success' => true, 'message' => 'success_message.health_condition_group_update'];
    }

    /**
     * @OA\Delete(
     *     path="/api/health-condition-group/{id}",
     *     tags={"HealthConditionGroup"},
     *     summary="Delete health condition group",
     *     operationId="deleteHealthConditionGroup",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Health condition group id",
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
     * @param HealthConditionGroup $healthConditionGroup
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(HealthConditionGroup $healthConditionGroup)
    {
        if (!$healthConditionGroup->healthConditions->count()) {
            $healthConditionGroup->delete();

            return ['success' => true, 'message' => 'success_message.health_condition_group_delete'];
        }

        return ['success' => false, 'message' => 'error_message.health_condition_group_delete'];
    }
}
