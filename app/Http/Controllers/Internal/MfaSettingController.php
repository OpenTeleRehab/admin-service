<?php

namespace App\Http\Controllers\Internal;

use App\Helpers\MfaSettingHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Internal\MfaSettingResource;
use App\Models\MfaSetting;
use App\Models\User;
use Illuminate\Http\Request;

class MfaSettingController extends Controller
{
    public function getMfaSettingsForTherapistService(Request $request)
    {
        $excludeId = $request->get('exclude_id');
        $organization = MfaSettingHelper::getOrganization();
        $query = MfaSetting::whereIn('role', [User::GROUP_THERAPIST, User::GROUP_PHC_WORKER])
            ->whereJsonContains('organizations', $organization->id);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $mfaSettings = $query->get();

        return response()->json(['data' => MfaSettingResource::collection($mfaSettings)]);
    }

    public function destroy($id)
    {
        MfaSetting::findOrFail($id)->delete();

        return response()->json(['message' => 'MFA setting deleted successfully.']);
    }
}
