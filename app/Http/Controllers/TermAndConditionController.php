<?php

namespace App\Http\Controllers;

use App\Events\ApplyTermAndConditionAutoTranslationEvent;
use App\Http\Resources\TermAndConditionResource;
use App\Models\Forwarder;
use App\Models\TermAndCondition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TermAndConditionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/term-condition",
     *     tags={"Term condition"},
     *     summary="Lists all term condition",
     *     operationId="termConditionList",
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
        $termAndConditions = TermAndCondition::all();

        return ['success' => true, 'data' => TermAndConditionResource::collection($termAndConditions)];
    }

    /**
     * @OA\Post(
     *     path="/api/term-condition",
     *     tags={"Term condition"},
     *     summary="Create term condition",
     *     operationId="createTermCondition",
     *     @OA\Parameter(
     *         name="version",
     *         in="query",
     *         description="Version",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Content",
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
        $termAndConditions = TermAndCondition::create([
            'version' => $request->get('version'),
            'content' => $request->get('content'),
            'status' => TermAndCondition::STATUS_DRAFT
        ]);

        // Add automatic translation for Term and Conditions.
        event(new ApplyTermAndConditionAutoTranslationEvent($termAndConditions));

        return ['success' => true, 'message' => 'success_message.team_and_condition_add'];
    }

    /**
     * @param string $id
     *
     * @return \App\Http\Resources\TermAndConditionResource
     */
    public function show($id)
    {
        $termAndCondition = TermAndCondition::findOrFail($id);
        return new TermAndConditionResource($termAndCondition);
    }

    /**
     * @OA\Put(
     *     path="/api/term-condition/{id}",
     *     tags={"Term condition"},
     *     summary="Update term condition",
     *     operationId="updateTermCondition",
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
     *         name="version",
     *         in="query",
     *         description="Version",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="content",
     *         in="query",
     *         description="Content",
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
     * @param string $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        $termAndCondition = TermAndCondition::findOrFail($id);
        $termAndCondition->update([
            'version' => $request->get('version'),
            'content' => $request->get('content'),
            'auto_translated' => false,
        ]);

        return ['success' => true, 'message' => 'success_message.team_and_condition_update'];
    }

    /**
     * @OA\Get(
     *     path="/api/user-term-condition",
     *     tags={"Term condition"},
     *     summary="Get user term condition",
     *     operationId="getUserTermCondition",
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
     * @return \App\Http\Resources\TermAndConditionResource
     */
    public function getUserTermAndCondition()
    {
        $termAndCondition = TermAndCondition::where('status', TermAndCondition::STATUS_PUBLISHED)
            ->orderBy('published_date', 'desc')
            ->firstOrFail();

        return new TermAndConditionResource($termAndCondition);
    }

    /**
     * @OA\Post(
     *     path="/api/term-condition/publish/{id}",
     *     tags={"Term condition"},
     *     summary="Publish term condition",
     *     operationId="publishUserTermCondition",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
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
     * @param string $id
     *
     * @return array
     */
    public function publish($id)
    {
        // Update the all previous published terms to expired.
        TermAndCondition::where('status', TermAndCondition::STATUS_PUBLISHED)
            ->update(['status' => TermAndCondition::STATUS_EXPIRED]);

        // Set the current term to published.
        TermAndCondition::findOrFail($id)
            ->update([
                'status' => TermAndCondition::STATUS_PUBLISHED,
                'published_date' => Carbon::now()
            ]);

        // Add required action to all users.
        Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/term-condition/send-re-consent');

        return ['success' => true, 'message' => 'success_message.team_and_condition_publish'];
    }

    /**
     * @OA\Get(
     *     path="/api/page/term-condition",
     *     tags={"Term condition"},
     *     summary="Get term condition page",
     *     operationId="getTermConditionPage",
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
     * @return \Illuminate\View\View
     */
    public function getTermAndConditionPage()
    {
        $page = TermAndCondition::where('status', TermAndCondition::STATUS_PUBLISHED)
            ->orderBy('published_date', 'desc')
            ->firstOrFail();

        $title = 'Terms of Services - OpenRehab';

        return view('templates.public', compact('page', 'title'));
    }
}
