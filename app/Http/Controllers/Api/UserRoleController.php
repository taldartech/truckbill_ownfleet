<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Resources\UserRoleResource;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserRoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UserRole::class);

        $roles = UserRole::query()
            ->withCount('permissions')
            ->orderBy('id')
            ->get();

        return UserRoleResource::collection($roles);
    }

    public function show(UserRole $userRole): UserRoleResource
    {
        $this->authorize('view', $userRole);

        $userRole->load('permissions');

        return new UserRoleResource($userRole);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, UserRole $userRole): UserRoleResource
    {
        $userRole->permissions()->sync($request->validated('permission_ids'));
        $userRole->load('permissions');

        return new UserRoleResource($userRole);
    }

    public function store(): JsonResponse
    {
        return response()->json([
            'message' => 'System roles cannot be created.',
        ], 403);
    }

    public function update(): JsonResponse
    {
        return response()->json([
            'message' => 'System role names cannot be changed. Update permissions instead.',
        ], 403);
    }

    public function destroy(): JsonResponse
    {
        return response()->json([
            'message' => 'System roles cannot be deleted.',
        ], 403);
    }
}
