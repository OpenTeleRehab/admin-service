<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    
    /**
     * Get countries by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $countryIds = $request->get('ids', []);
        $countries = Country::whereIn('id', $countryIds)->select('id', 'name')->get();
        return ['success' => true, 'data' => $countries];
    }
}
