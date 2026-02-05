<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\PhcService;
use Illuminate\Http\Request;

class PhcServiceController extends Controller
{
    
    /**
     * Get phc services by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $phcServiceIds = $request->get('ids', []);
        $phcServices = PhcService::whereIn('id', $phcServiceIds)->select('id', 'name')->get();
        return ['success' => true, 'data' => $phcServices];
    }
}
