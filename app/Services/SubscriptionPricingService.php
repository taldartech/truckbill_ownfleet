<?php

namespace App\Services;

use App\Models\TenantSubscription;
use App\Support\Money;
use App\Support\SubscriptionProration;
use App\Tenant;
use Carbon\Carbon;

class SubscriptionPricingService
{
    public function __construct(
        private readonly AdminApiClient $adminApi,
        private readonly SubscriptionUsageService $usageService
    ) {}

    /**
     * @param  array<string, mixed>  $catalog
     * @return array{addon_branches: int, addon_users: int}
     */
    public function minimumRenewalAddons(Tenant $tenant, array $catalog): array
    {
        return $this->usageService->minimumRenewalAddons($tenant, $catalog);
    }

    public function quote(Tenant $tenant, array $input): array
    {
        $catalogPayload = $this->adminApi->getCatalog();
        $catalog = $catalogPayload['catalog'];
        $gstRate = (float) $catalogPayload['gst_rate_percent'];

        $purchaseType = $input['purchase_type'];
        $addonBranches = (int) ($input['addon_branches'] ?? 0);
        $addonUsers = (int) ($input['addon_users'] ?? 0);
        $addonTrucks = (int) ($input['addon_trucks'] ?? 0);
        $addonLrCredits = (int) ($input['addon_lr_credits'] ?? 0);

        $subscription = $tenant->subscription;
        $usage = $this->usageService->usageForTenant($tenant);
        $minimumAddons = $this->usageService->minimumRenewalAddons($tenant, $catalog);
        $blockingErrors = [];
        $lineItems = [];
        $remainingDays = $purchaseType === 'addon' && $subscription
            ? $subscription->remainingDaysInclusive()
            : null;

        if ($purchaseType === 'renewal') {
            $lineItems[] = [
                'label' => 'Annual base plan',
                'amount' => (float) $catalog['base_price'],
            ];
            $blockingErrors = array_merge(
                $blockingErrors,
                $this->renewalCapacityErrors($catalog, $usage, $addonBranches, $addonUsers, $addonTrucks, $tenant),
            );
        } elseif (! $subscription?->isActive()) {
            $blockingErrors[] = 'Add-ons are only available for active subscriptions.';
        }

        if ($addonBranches > 0) {
            $lineItems[] = $this->addOnLineItem(
                $purchaseType,
                $remainingDays,
                $addonBranches,
                (float) $catalog['addon_branch_price'],
                'Branch add-ons',
            );
        }
        if ($addonUsers > 0) {
            $lineItems[] = $this->addOnLineItem(
                $purchaseType,
                $remainingDays,
                $addonUsers,
                (float) $catalog['addon_user_price'],
                'User add-ons',
            );
        }
        if ($addonTrucks > 0) {
            $lineItems[] = $this->addOnLineItem(
                $purchaseType,
                $remainingDays,
                $addonTrucks,
                (float) ($catalog['addon_truck_price'] ?? 0),
                'Vehicle add-ons',
            );
        }

        $subtotal = Money::round(collect($lineItems)->sum('amount'));
        $promoDiscount = 0.0;
        $promoSnapshot = null;

        if (! empty($input['promo_code']) && ($input['promo_validated'] ?? false)) {
            $promoResult = $this->adminApi->validatePromo($input['promo_code']);
            if ($promoResult['valid'] ?? false) {
                $promo = $promoResult['promo'];
                $promoSnapshot = $promo;
                $promoDiscount = $promo['discount_type'] === 'percentage'
                    ? Money::round($subtotal * ((float) $promo['discount_value'] / 100))
                    : Money::round(min($subtotal, (float) $promo['discount_value']));
                $lineItems[] = ['label' => 'Promo discount', 'amount' => -$promoDiscount];
            }
        }

        $taxable = Money::round(max(0, $subtotal - $promoDiscount));
        $gstAmount = Money::round($taxable * ($gstRate / 100));
        $total = Money::round($taxable + $gstAmount);

        return [
            'purchase_type' => $purchaseType,
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'promo_discount' => $promoDiscount,
            'gst_amount' => $gstAmount,
            'total_amount' => $total,
            'gst_rate_percent' => $gstRate,
            'current_usage' => $usage,
            'minimum_addons_required' => $minimumAddons,
            'can_checkout' => empty($blockingErrors) && $total > 0,
            'blocking_errors' => $blockingErrors,
            'catalog_snapshot' => $catalog,
            'promo_snapshot' => $promoSnapshot,
            'quantities' => [
                'addon_branches' => $addonBranches,
                'addon_users' => $addonUsers,
                'addon_trucks' => $addonTrucks,
                'addon_lr_credits' => $addonLrCredits,
            ],
            'proration' => $remainingDays !== null ? [
                'remaining_days' => $remainingDays,
                'annual_days' => SubscriptionProration::ANNUAL_DAYS,
            ] : null,
        ];
    }

    /**
     * @return array{label: string, amount: float}
     */
    private function addOnLineItem(
        string $purchaseType,
        ?int $remainingDays,
        int $quantity,
        float $annualUnitPrice,
        string $labelPrefix,
    ): array {
        $annualAmount = Money::round($quantity * $annualUnitPrice);

        if ($purchaseType === 'addon' && $remainingDays !== null) {
            $amount = SubscriptionProration::prorateAnnualAmount($annualAmount, $remainingDays);
            $label = "{$labelPrefix} ({$quantity}, {$remainingDays} days prorated)";

            return ['label' => $label, 'amount' => $amount];
        }

        return ['label' => "{$labelPrefix} ({$quantity})", 'amount' => $annualAmount];
    }

    public function createTrialSubscription(Tenant $tenant): TenantSubscription
    {
        $catalog = $this->resolveCatalogForTrial();
        $startsOn = now()->toDateString();
        $trialDays = (int) config('subscription.trial_days', 15);
        $endsOn = Carbon::parse($startsOn)->addDays($trialDays)->toDateString();

        return TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'status' => 'trial',
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'trial_ends_on' => $endsOn,
                'included_branches' => (int) ($catalog['included_branches'] ?? 2),
                'users_per_branch' => (int) ($catalog['users_per_branch'] ?? 3),
                'included_lr_credits' => (int) ($catalog['included_lr_credits'] ?? 0),
                'included_trucks' => (int) ($catalog['included_trucks'] ?? 50),
                'lr_credits_remaining' => (int) ($catalog['included_lr_credits'] ?? 0),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveCatalogForTrial(): array
    {
        try {
            $catalogPayload = $this->adminApi->getCatalog();

            if (! empty($catalogPayload['catalog']) && is_array($catalogPayload['catalog'])) {
                return $catalogPayload['catalog'];
            }
        } catch (\Throwable) {
            // Admin API is offline or unreachable; fall back to defaults.
        }

        return (array) config('subscription.default_catalog', [
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @param  array<string, int>  $usage
     * @return list<string>
     */
    private function renewalCapacityErrors(
        array $catalog,
        array $usage,
        int $addonBranches,
        int $addonUsers,
        int $addonTrucks,
        Tenant $tenant,
    ): array {
        $errors = [];
        $includedBranches = (int) $catalog['included_branches'];
        $perBranch = (int) $catalog['users_per_branch'];
        $branchSlots = $includedBranches + $addonBranches;

        if ($branchSlots < $usage['branches']) {
            $required = $usage['branches'] - $includedBranches;
            $errors[] = "Add at least {$required} branch add-on(s), or delete extra branches before renewing.";
        }

        $maxTotal = ($branchSlots * $perBranch) + $addonUsers;
        if ($maxTotal < $usage['total_users']) {
            $errors[] = 'Add more user add-ons, or remove users before renewing.';
        }

        $overflowRequired = $this->usageService->overflowUsersUsed($tenant, $perBranch);
        if ($addonUsers < $overflowRequired) {
            $errors[] = "Add at least {$overflowRequired} user add-on(s) for branches over the per-branch limit, or reassign or remove users.";
        }

        $includedTrucks = (int) ($catalog['included_trucks'] ?? 0);
        $truckSlots = $includedTrucks + $addonTrucks;
        if ($truckSlots < $usage['trucks']) {
            $required = $usage['trucks'] - $includedTrucks;
            $errors[] = "Add at least {$required} vehicle add-on(s), or remove vehicles before renewing.";
        }

        return $errors;
    }
}
