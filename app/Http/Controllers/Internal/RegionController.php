<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    
    /**
     * Get regions by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $regionIds = $request->get('ids', []);
        $regions = Region::whereIn('id', $regionIds)->select('id', 'name')->get();
        return ['success' => true, 'data' => $regions];
    }
}
