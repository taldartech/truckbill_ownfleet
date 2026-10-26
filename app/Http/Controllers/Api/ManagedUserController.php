<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Http\Resources\ManagedUserResource;
use App\Http\Resources\UserRoleResource;
use App\Services\SubscriptionGuard;
use App\Support\RoleHierarchy;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ManagedUserController extends Controller
{
    public function __construct(
        private readonly SubscriptionGuard $subscriptionGuard,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $actor = $request->user();

        $query = User::query()
            ->with(['userRole', 'tenant', 'branch'])
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->when($request->filled('user_role_id'), fn ($builder) => $builder->where('user_role_id', (int) $request->input('user_role_id')))
            ->orderBy('name');

        if ($actor !== null && RoleHierarchy::managesSuperAdmins($actor)) {
            $query->where('user_role_id', UserRole::SUPER_ADMIN);
        } elseif ($actor !== null && RoleHierarchy::managesTenantUsers($actor)) {
            $tenantIds = RoleHierarchy::ownedTenantIds($actor);

            $query
                ->whereIn('tenant_id', $tenantIds)
                ->whereNotIn('user_role_id', [UserRole::MASTER_ADMIN, UserRole::SUPER_ADMIN]);
        }

        return ManagedUserResource::collection($query->paginate($perPage));
    }

    public function store(StoreManagedUserRequest $request): ManagedUserResource
    {
        $validated = $request->validated();
        $roleId = (int) $validated['user_role_id'];

        if ($roleId === UserRole::SUPER_ADMIN && ! app()->environment('testing')) {
            abort(422, 'Super Admin accounts must be provisioned through the central onboarding API.');
        }

        $tenantId = $roleId === UserRole::SUPER_ADMIN
            ? null
            : ($validated['tenant_id'] ?? $request->user()?->resolvedCompany()?->id);

        if ($tenantId !== null) {
            $tenant = Tenant::query()->with('subscription')->findOrFail($tenantId);
            $this->subscriptionGuard->assertCanCreateUser($tenant);

            if (RoleHierarchy::requiresBranchAssignment($roleId) && ! empty($validated['branch_id'])) {
                $this->subscriptionGuard->assertCanAssignUserToBranch(
                    $tenant,
                    (string) $validated['branch_id'],
                );
            }
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'user_role_id' => $roleId,
            'tenant_id' => $roleId === UserRole::SUPER_ADMIN
                ? null
                : ($validated['tenant_id'] ?? null),
            'branch_id' => RoleHierarchy::requiresBranchAssignment($roleId)
                ? ($validated['branch_id'] ?? null)
                : null,
            'document_alerts_live' => $roleId === UserRole::SUPER_ADMIN
                ? false
                : (bool) ($validated['document_alerts_live'] ?? false),
            'document_alerts_email' => $roleId === UserRole::SUPER_ADMIN
                ? false
                : (bool) ($validated['document_alerts_email'] ?? false),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $user->load(['userRole', 'tenant', 'branch']);

        return new ManagedUserResource($user);
    }

    public function show(User $managedUser): ManagedUserResource
    {
        $this->authorize('view', $managedUser);

        $managedUser->load(['userRole', 'tenant', 'branch']);

        return new ManagedUserResource($managedUser);
    }

    public function update(UpdateManagedUserRequest $request, User $managedUser): ManagedUserResource
    {
        $validated = $request->validated();

        if (RoleHierarchy::isProtectedRole((int) $managedUser->user_role_id)) {
            unset($validated['user_role_id'], $validated['tenant_id']);
        }

        if (isset($validated['user_role_id'])) {
            $roleId = (int) $validated['user_role_id'];

            if ($roleId === UserRole::SUPER_ADMIN) {
                $validated['tenant_id'] = null;
                $validated['branch_id'] = null;
            } elseif (! RoleHierarchy::requiresBranchAssignment($roleId)) {
                $validated['branch_id'] = null;
            }
        }

        if (array_key_exists('tenant_id', $validated)
            && $validated['tenant_id'] !== ($managedUser->tenant_id ?? null)
            && ! array_key_exists('branch_id', $validated)) {
            $validated['branch_id'] = null;
        }

        if (array_key_exists('password', $validated) && ($validated['password'] === null || $validated['password'] === '')) {
            unset($validated['password']);
        }

        unset($validated['password_confirmation']);

        $roleId = (int) ($validated['user_role_id'] ?? $managedUser->user_role_id);
        $tenantId = $validated['tenant_id'] ?? $managedUser->tenant_id;
        $branchId = array_key_exists('branch_id', $validated)
            ? $validated['branch_id']
            : $managedUser->branch_id;

        if ($tenantId !== null && $branchId !== null && RoleHierarchy::requiresBranchAssignment($roleId)) {
            $tenant = Tenant::query()->with('subscription')->findOrFail($tenantId);
            $this->subscriptionGuard->assertCanAssignUserToBranch(
                $tenant,
                (string) $branchId,
                (string) $managedUser->id,
            );
        }

        $validated['updated_by'] = $request->user()?->id;

        $managedUser->update($validated);
        $managedUser->load(['userRole', 'tenant', 'branch']);

        return new ManagedUserResource($managedUser);
    }

    public function destroy(Request $request, User $managedUser): Response
    {
        $this->authorize('delete', $managedUser);

        if (! $request->has('deleted_reason') && $request->has('remark')) {
            $request->merge(['deleted_reason' => $request->input('remark')]);
        }

        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);

        $managedUser->deleted_by = $request->user()?->id;
        $managedUser->deleted_reason = $validated['deleted_reason'];
        $managedUser->save();

        $managedUser->delete();

        return response()->noContent();
    }

    public function assignableRoles(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $ids = $actor !== null ? RoleHierarchy::assignableRoleIds($actor) : [];

        $roles = UserRole::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        return UserRoleResource::collection($roles);
    }
}
