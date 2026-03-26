<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    
    /**
     * Get provinces by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $provinceIds = $request->get('ids', []);
        $provinces = Province::whereIn('id', $provinceIds)->select('id', 'name')->get();
        return ['success' => true, 'data' => $provinces];
    }
}
