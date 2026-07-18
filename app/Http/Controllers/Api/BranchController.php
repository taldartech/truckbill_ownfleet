<?php

namespace App\Http\Controllers\Api;

use App\Branch;
use App\BranchSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Support\BranchDeletionImpact;
use App\Support\RoleHierarchy;
use App\Support\SelectedBranchSession;
use App\Support\SelectedCompanySession;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        /** @var User $user */
        $user = $request->user();
        $companyIds = RoleHierarchy::ownedTenantIds($user);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);

        $query = Branch::query()
            ->with(['company.state', 'state', 'settings'])
            ->whereIn('company_id', $companyIds)
            ->orderBy('branch_name');

        if ($request->filled('company_id')) {
            $companyId = (string) $request->input('company_id');

            if (in_array($companyId, $companyIds, true)) {
                $query->where('company_id', $companyId);
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('branch_name', 'like', "%{$search}%")
                    ->orWhere('branch_address', 'like', "%{$search}%")
                    ->orWhere('branch_contact_number', 'like', "%{$search}%")
                    ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return BranchResource::collection($query->paginate($perPage));
    }

    public function store(StoreBranchRequest $request): BranchResource
    {
        $branch = DB::transaction(function () use ($request): Branch {
            $validated = collect($request->validated())
                ->except(['branch_logo', 'number_settings'])
                ->all();
            $validated['head_office'] = $request->boolean('head_office');
            $validated['inherit_company_gst'] = $request->boolean('inherit_company_gst', true);
            $validated['use_company_logo'] = $request->boolean('use_company_logo', true);

            if ($validated['head_office']) {
                Branch::query()
                    ->where('company_id', $validated['company_id'])
                    ->update(['head_office' => false]);
            }

            if ($validated['use_company_logo']) {
                $validated['branch_logo'] = null;
            }

            $branch = Branch::query()->create($validated);

            BranchSetting::createForBranch($branch->id, $request->numberSettingsPayload());

            /** @var UploadedFile|null $logo */
            $logo = $request->file('branch_logo');

            if (! $validated['use_company_logo'] && $logo instanceof UploadedFile) {
                $branch->branch_logo = $branch->storeLogo($logo);
                $branch->save();
            }

            return $branch;
        });

        return new BranchResource($branch->load(['company', 'settings']));
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch->load(['company', 'settings']));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch = DB::transaction(function () use ($request, $branch): Branch {
            $validated = collect($request->validated())
                ->except(['branch_logo', 'remove_logo', 'number_settings'])
                ->all();

            if ($request->has('head_office')) {
                $validated['head_office'] = $request->boolean('head_office');
            }

            if ($request->has('inherit_company_gst')) {
                $validated['inherit_company_gst'] = $request->boolean('inherit_company_gst');
            }

            if ($request->has('use_company_logo')) {
                $validated['use_company_logo'] = $request->boolean('use_company_logo');
            }

            $companyId = (string) ($validated['company_id'] ?? $branch->company_id);

            if (($validated['head_office'] ?? $branch->head_office) === true) {
                Branch::query()
                    ->where('company_id', $companyId)
                    ->whereKeyNot($branch->id)
                    ->update(['head_office' => false]);
            }

            $branch->fill($validated);

            $useCompanyLogo = (bool) ($validated['use_company_logo'] ?? $branch->use_company_logo);

            /** @var UploadedFile|null $logo */
            $logo = $request->file('branch_logo');

            if ($useCompanyLogo) {
                $branch->deleteLogo();
                $branch->use_company_logo = true;
            } elseif ($request->boolean('remove_logo')) {
                $branch->deleteLogo();
            } elseif ($logo instanceof UploadedFile) {
                $branch->deleteLogo();
                $branch->branch_logo = $branch->storeLogo($logo);
            }

            $branch->save();

            $settingsPayload = $request->numberSettingsPayload();

            if ($settingsPayload !== []) {
                $settings = $branch->settings()->first();

                if ($settings === null) {
                    BranchSetting::createForBranch($branch->id, $settingsPayload);
                } else {
                    $settings->fill($settingsPayload)->save();
                }
            }

            return $branch;
        });

        return new BranchResource($branch->load(['company', 'settings']));
    }

    public function destroy(Request $request, Branch $branch): Response|JsonResponse
    {
        $this->authorize('delete', $branch);

        DB::transaction(function () use ($branch): void {
            User::query()
                ->where('branch_id', $branch->id)
                ->update(['branch_id' => null]);

            if (SelectedBranchSession::id() === $branch->id) {
                SelectedBranchSession::forget();
            }

            $branch->delete();
        });

        $request->user()?->loadMissing(['userRole', 'tenant', 'branch']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Branch deleted.',
                'user' => $request->user() !== null
                    ? new \App\Http\Resources\UserResource($request->user())
                    : null,
            ]);
        }

        return response()->noContent();
    }

    public function deletionImpact(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        return response()->json([
            'branch_id' => $branch->id,
            'branch_name' => $branch->branch_name,
            ...BranchDeletionImpact::forBranch($branch),
        ]);
    }

    public function enter(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('enter', $branch);

        SelectedCompanySession::set((string) $branch->company_id);
        SelectedBranchSession::set((int) $branch->id);

        $request->user()?->loadMissing(['userRole', 'tenant', 'branch']);

        return response()->json([
            'message' => 'Branch selected.',
            'selected_branch_id' => $branch->id,
            'selected_tenant_id' => $branch->company_id,
            'selected_company_id' => $branch->company_id,
            'user' => $request->user() !== null
                ? new \App\Http\Resources\UserResource($request->user())
                : null,
        ]);
    }

    public function switch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        /** @var Branch $branch */
        $branch = Branch::query()->findOrFail($validated['branch_id']);

        $this->authorize('enter', $branch);

        SelectedCompanySession::set((string) $branch->company_id);
        SelectedBranchSession::set((int) $branch->id);

        $request->user()?->loadMissing(['userRole', 'tenant', 'branch']);

        return response()->json([
            'message' => 'Branch switched.',
            'selected_branch_id' => $branch->id,
            'selected_tenant_id' => $branch->company_id,
            'selected_company_id' => $branch->company_id,
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

        SelectedBranchSession::forget();

        $user->loadMissing(['userRole', 'tenant', 'branch']);

        return response()->json([
            'message' => 'Branch context cleared.',
            'user' => new \App\Http\Resources\UserResource($user),
        ]);
    }
}
