<?php

namespace App\Http\Controllers\Api;

use App\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Support\RoleHierarchy;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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
            ->with('company')
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
            $validated = $request->validated();
            $validated['head_office'] = $request->boolean('head_office');

            if ($validated['head_office']) {
                Branch::query()
                    ->where('company_id', $validated['company_id'])
                    ->update(['head_office' => false]);
            }

            return Branch::query()->create($validated);
        });

        return new BranchResource($branch->load('company'));
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch->load('company'));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch = DB::transaction(function () use ($request, $branch): Branch {
            $validated = $request->validated();

            if ($request->has('head_office')) {
                $validated['head_office'] = $request->boolean('head_office');
            }

            $companyId = (string) ($validated['company_id'] ?? $branch->company_id);

            if (($validated['head_office'] ?? $branch->head_office) === true) {
                Branch::query()
                    ->where('company_id', $companyId)
                    ->whereKeyNot($branch->id)
                    ->update(['head_office' => false]);
            }

            $branch->fill($validated);
            $branch->save();

            return $branch;
        });

        return new BranchResource($branch->load('company'));
    }

    public function destroy(Branch $branch): Response
    {
        $this->authorize('delete', $branch);
        $branch->delete();

        return response()->noContent();
    }
}
