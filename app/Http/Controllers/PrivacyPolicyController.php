<?php

namespace App\Http\Controllers;

use App\Events\ApplyPrivacyPolicyAutoTranslationEvent;
use App\Http\Resources\PrivacyPolicyResource;
use App\Http\Resources\TermAndConditionResource;
use App\Models\Forwarder;
use App\Models\PrivacyPolicy;
use App\Models\TermAndCondition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PrivacyPolicyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/privacy-policy",
     *     tags={"Privacy Policy"},
     *     summary="Lists all privacy policy",
     *     operationId="PrivacyPolicyList",
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
        $privacyPolicies = PrivacyPolicy::all();

        return ['success' => true, 'data' => PrivacyPolicyResource::collection($privacyPolicies)];
    }

    /**
     * @OA\Post(
     *     path="/api/privacy-policy",
     *     tags={"Privacy Policy"},
     *     summary="Create privacy policy",
     *     operationId="CreatePrivacyPolicy",
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
        $privacyPolicy = PrivacyPolicy::create([
            'version' => $request->get('version'),
            'content' => $request->get('content'),
            'status' => PrivacyPolicy::STATUS_DRAFT
        ]);

        // Add automatic translation for Privacy Policy.
        event(new ApplyPrivacyPolicyAutoTranslationEvent($privacyPolicy));

        return ['success' => true, 'message' => 'success_message.privacy_policy_add'];
    }

    /**
     * @param int $id
     *
     * @return \App\Http\Resources\PrivacyPolicyResource
     */
    public function show($id)
    {
        $privacyPolicy = PrivacyPolicy::findOrFail($id);
        return new PrivacyPolicyResource($privacyPolicy);
    }

    /**
     * @OA\Put(
     *     path="/api/privacy-policy/{id}",
     *     tags={"Privacy Policy"},
     *     summary="Update privacy policy",
     *     operationId="UpdatePrivacyPolicy",
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
        $privacyPolicy = PrivacyPolicy::findOrFail($id);
        $privacyPolicy->update([
            'version' => $request->get('version'),
            'content' => $request->get('content'),
            'auto_translated' => false,
        ]);

        return ['success' => true, 'message' => 'success_message.privacy_policy_update'];
    }

    /**
     * @OA\Get(
     *     path="/api/user-privacy-policy",
     *     tags={"Privacy Policy"},
     *     summary="Get user privacy policy",
     *     operationId="getUserPrivacyPolicy",
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
    public function getUserPrivacyPolicy()
    {
        $privacyPolicy = PrivacyPolicy::where('status', TermAndCondition::STATUS_PUBLISHED)
            ->orderBy('published_date', 'desc')
            ->firstOrFail();

        return new TermAndConditionResource($privacyPolicy);
    }

    /**
     * @OA\Post(
     *     path="/api/privacy-policy/publish/{id}",
     *     tags={"Privacy Policy"},
     *     summary="Publish privacy policy",
     *     operationId="publishPrivacyPolicy",
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
     * @param string $id
     *
     * @return array
     */
    public function publish($id)
    {
        // Update the all previous published terms to expired.
        PrivacyPolicy::where('status', PrivacyPolicy::STATUS_PUBLISHED)
            ->update(['status' => PrivacyPolicy::STATUS_EXPIRED]);

        // Set the current term to published.
        PrivacyPolicy::findOrFail($id)
            ->update([
                'status' => PrivacyPolicy::STATUS_PUBLISHED,
                'published_date' => Carbon::now()
            ]);

        // Add required action to all users.
        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/term-condition/send-re-consent');

        return ['success' => true, 'message' => 'success_message.privacy_policy_publish'];
    }

    /**
     * @OA\Get(
     *     path="/api/page/privacy",
     *     tags={"Privacy Policy"},
     *     summary="Getprivacy page",
     *     operationId="getPrivacyPage",
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
    public function getPrivacyPage()
    {
        $page = PrivacyPolicy::where('status', PrivacyPolicy::STATUS_PUBLISHED)
            ->orderBy('published_date', 'desc')
            ->firstOrFail();

        $title = 'Privacy Policy - OpenRehab';

        return view('templates.public', compact('page', 'title'));
    }
}
