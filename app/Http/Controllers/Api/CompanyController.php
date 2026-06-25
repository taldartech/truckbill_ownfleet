<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Support\CompanyProvisioner;
use App\Support\SelectedBranchSession;
use App\Support\SelectedCompanySession;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tenant::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);

        $query = Tenant::query()->orderByDesc('created_at');

        /** @var User $user */
        $user = $request->user();

        if ($user->user_role_id === UserRole::SUPER_ADMIN) {
            $query->where('owner_user_id', $user->id);
        }

        return CompanyResource::collection($query->paginate($perPage));
    }

    public function store(StoreCompanyRequest $request): CompanyResource
    {
        $this->authorize('create', Tenant::class);

        /** @var User $user */
        $user = $request->user();

        $tenant = CompanyProvisioner::create($request->validated(), $user);

        return new CompanyResource($tenant);
    }

    public function show(Tenant $company): CompanyResource
    {
        $this->authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request, Tenant $company): CompanyResource
    {
        $this->authorize('update', $company);

        if ($request->user()?->user_role_id === UserRole::MASTER_ADMIN && $request->has('is_blocked')) {
            $this->authorize('block', $company);
        }

        $company->fill($request->validated());
        $company->save();

        return new CompanyResource($company->fresh());
    }

    public function enter(Request $request, Tenant $company): JsonResponse
    {
        $this->authorize('enter', $company);

        SelectedCompanySession::set($company->id);

        $request->user()?->loadMissing(['userRole', 'tenant']);

        return response()->json([
            'message' => 'Company selected.',
            'selected_tenant_id' => $company->id,
            'selected_company_id' => $company->id,
            'user' => $request->user() !== null
                ? new \App\Http\Resources\UserResource($request->user())
                : null,
        ]);
    }

    public function exit(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->hasRole('super_admin')) {
            abort(403);
        }

        SelectedCompanySession::forget();
        SelectedBranchSession::forget();

        $user->loadMissing(['userRole', 'tenant']);

        return response()->json([
            'message' => 'Company context cleared.',
            'user' => new \App\Http\Resources\UserResource($user),
        ]);
    }
}
