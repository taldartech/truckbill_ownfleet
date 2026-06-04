<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PermissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Permission::class);

        $perPage = min(max((int) $request->input('per_page', 50), 1), 500);

        $permissions = Permission::query()
            ->withCount('userRoles')
            ->when($request->filled('group'), fn ($query) => $query->where('group', $request->string('group')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('slug', 'like', $search)
                        ->orWhere('name', 'like', $search);
                });
            })
            ->orderBy('group')
            ->orderBy('slug')
            ->paginate($perPage);

        return PermissionResource::collection($permissions);
    }

    public function store(StorePermissionRequest $request): PermissionResource
    {
        $permission = Permission::query()->create($request->validated());

        return new PermissionResource($permission);
    }

    public function show(Permission $permission): PermissionResource
    {
        $this->authorize('view', $permission);

        return new PermissionResource($permission->loadCount('userRoles'));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): PermissionResource
    {
        $permission->update($request->validated());

        return new PermissionResource($permission->fresh());
    }

    public function destroy(Permission $permission): Response
    {
        $this->authorize('delete', $permission);

        $permission->userRoles()->detach();
        $permission->delete();

        return response()->noContent();
    }
}
