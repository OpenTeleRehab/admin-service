<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Internal\UserResource;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    /**
     * @param User $user
     * @return UserResource
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /**
     * Get users by ids.
     *
     * @return UserResource
     */
    public function getByIds()
    {
        $ids = request()->get('ids', []);
        $users = User::whereIn('id', $ids)->get();
        return UserResource::collection($users);
    }

    /**
     * Get user regions.
     *
     * @param Request $request
     *
     * @return array
     */
    public function getByRegions(Request $request)
    {
        $regionIds = $request->get('region_ids', []);
        $userIds = User::whereHas('regions', function ($query) use ($regionIds) {
            $query->whereIn('id', $regionIds);
        })
        ->orWhereIn('region_id', $regionIds)
        ->pluck('id')
        ->unique()
        ->values();
        return ['success' => true, 'data' => $userIds];
    }

    /**
     * Get users by name.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByName(Request $request)
    {
        $name = $request->get('name');
        $users = $users = User::where('first_name', 'like', '%' . $name . '%')
            ->orWhere('last_name', 'like', '%' . $name . '%')
            ->get();

        return ['success' => true, 'data' => $users];
    }

    /**
     * Get users by type.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByType(Request $request)
    {
        $type = $request->get('type');
        $users = User::where('type', $type)->get();

        return ['success' => true, 'data' => $users];
    }
}
