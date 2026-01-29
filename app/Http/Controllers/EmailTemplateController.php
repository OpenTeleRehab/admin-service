<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => EmailTemplateResource::collection(EmailTemplate::all()),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param EmailTemplate $emailTemplate
     * @return EmailTemplateResource
     */
    public function show(EmailTemplate $emailTemplate)
    {
        return new EmailTemplateResource($emailTemplate);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate)
    {
        $emailTemplate->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'success_message.email_template_update',
        ]);
    }

    /**
     * @param string $prefix
     * @return EmailTemplateResource
     */
    public function getByPrefix(string $prefix)
    {
        $emailTemplate = EmailTemplate::where('prefix', $prefix)->firstOrFail();

        return new EmailTemplateResource($emailTemplate);
    }
}
