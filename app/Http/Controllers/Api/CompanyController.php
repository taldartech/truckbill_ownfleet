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
use Illuminate\Http\UploadedFile;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tenant::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);

        $query = Tenant::query()
            ->with(['state', 'subscription'])
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('gst_number', 'like', $search)
                        ->orWhere('pan_number', 'like', $search);
                });
            })
            ->orderByDesc('created_at');

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

        $validated = collect($request->validated())->except(['company_logo'])->all();
        $tenant = CompanyProvisioner::create($validated, $user);

        /** @var UploadedFile|null $logo */
        $logo = $request->file('company_logo');

        if ($logo instanceof UploadedFile) {
            $tenant->company_logo = $tenant->storeLogo($logo);
            $tenant->save();
        }

        return new CompanyResource($tenant->loadMissing(['state', 'subscription']));
    }

    public function show(Tenant $company): CompanyResource
    {
        $this->authorize('view', $company);

        return new CompanyResource($company->loadMissing(['state', 'subscription']));
    }

    public function update(UpdateCompanyRequest $request, Tenant $company): CompanyResource
    {
        $this->authorize('update', $company);

        if ($request->user()?->user_role_id === UserRole::MASTER_ADMIN && $request->has('is_blocked')) {
            $this->authorize('block', $company);
        }

        $validated = collect($request->validated())->except(['company_logo', 'remove_logo'])->all();
        $company->fill($validated);

        /** @var UploadedFile|null $logo */
        $logo = $request->file('company_logo');

        if ($request->boolean('remove_logo')) {
            $company->deleteLogo();
        } elseif ($logo instanceof UploadedFile) {
            $company->deleteLogo();
            $company->company_logo = $company->storeLogo($logo);
        }

        $company->save();

        return new CompanyResource($company->fresh()->load(['state', 'subscription']));
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
