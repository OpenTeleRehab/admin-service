<?php

namespace App\Http\Controllers\Internal;

use App\Helpers\MfaSettingHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Internal\MfaSettingResource;
use App\Models\MfaSetting;
use App\Models\User;

class MfaSettingController extends Controller
{
    public function getMfaSettingsForTherapistService()
    {
        $organization = MfaSettingHelper::getOrganization();
        $mfaSettings = MfaSetting::whereIn('role', [User::GROUP_THERAPIST, User::GROUP_PHC_WORKER])
            ->whereJsonContains('organizations', $organization->id)
            ->get();

        return response()->json(['data' => MfaSettingResource::collection($mfaSettings)]);
    }

    public function destroy($id)
    {
        MfaSetting::findOrFail($id)->delete();

        return response()->json(['message' => 'MFA setting deleted successfully.']);
    }
}
