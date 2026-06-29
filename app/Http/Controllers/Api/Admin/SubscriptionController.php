<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\TenantSubscription;
use App\Models\UpcomingSubscription;
use App\Services\SubscriptionUsageService;
use App\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionUsageService $usageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()->with('subscription')->orderBy('name');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        $companies = $query->paginate((int) $request->input('per_page', 25));

        $data = $companies->through(function (Tenant $tenant) {
            $subscription = $tenant->subscription;

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $subscription?->status,
                'ends_on' => $subscription?->ends_on?->toDateString(),
                'usage' => $this->usageService->usageForTenant($tenant),
            ];
        });

        return response()->json($data);
    }

    public function search(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('subscription')->findOrFail($id);
        $subscription = $tenant->subscription;

        return response()->json([
            'company' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'subscription' => $subscription,
            'usage' => $this->usageService->usageForTenant($tenant),
            'payments' => SubscriptionPayment::query()
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'upcoming' => UpcomingSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->get(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::with('subscription')->findOrFail($id);
        $subscription = $tenant->subscription ?? TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'status' => 'trial',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['trial', 'active', 'expired', 'cancelled'])],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date'],
            'addon_branches' => ['sometimes', 'integer', 'min:0'],
            'addon_users' => ['sometimes', 'integer', 'min:0'],
            'addon_trucks' => ['sometimes', 'integer', 'min:0'],
            'addon_lr_credits' => ['sometimes', 'integer', 'min:0'],
            'lr_credits_remaining' => ['sometimes', 'integer', 'min:0'],
        ]);

        $subscription->update($validated);

        SubscriptionEvent::create([
            'tenant_id' => $tenant->id,
            'event_type' => 'admin_override',
            'metadata' => $validated,
        ]);

        return response()->json(['subscription' => $subscription->fresh()]);
    }
}
