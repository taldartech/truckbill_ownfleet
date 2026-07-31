<?php

namespace App\Services;

use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\TenantSubscription;
use App\Models\UpcomingSubscription;
use App\Support\Money;
use App\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;

class SubscriptionCheckoutService
{
    public function __construct(
        private readonly AdminApiClient $adminApi,
        private readonly SubscriptionPricingService $pricingService,
        private readonly SubscriptionUsageService $usageService
    ) {}

    public function createCheckoutOrder(Tenant $tenant, array $input, string $actorUserId): array
    {
        $quote = $this->pricingService->quote($tenant, $input);

        if (! $quote['can_checkout']) {
            throw new \InvalidArgumentException(implode(' ', $quote['blocking_errors'] ?: ['Checkout is not available.']));
        }

        $checkoutMeta = $this->razorpayCheckoutMeta($tenant, $actorUserId, $quote);
        $description = $this->razorpayDescription($quote);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'type' => $input['purchase_type'],
            'status' => 'pending',
            'promo_code' => $input['promo_code'] ?? null,
            'quantities' => $quote['quantities'],
            'subtotal' => $quote['subtotal'],
            'promo_discount' => $quote['promo_discount'],
            'gst_amount' => $quote['gst_amount'],
            'total_amount' => $quote['total_amount'],
            'catalog_snapshot' => $quote['catalog_snapshot'],
            'usage_snapshot_at_purchase' => $quote['current_usage'],
        ]);

        $api = new Api(config('subscription.razorpay_key'), config('subscription.razorpay_secret'));

        try {
            $orderPayload = [
                'receipt' => 'sub_'.$payment->id.'_'.time(),
                'amount' => Money::toPaise($quote['total_amount']),
                'currency' => 'INR',
            ];
            if ($checkoutMeta['notes'] !== []) {
                $orderPayload['notes'] = $checkoutMeta['notes'];
            }
            $order = $api->order->create($orderPayload);
        } catch (\Throwable $exception) {
            $payment->delete();

            throw $exception;
        }

        $payment->update(['razorpay_order_id' => $order['id']]);

        SubscriptionEvent::create([
            'tenant_id' => $tenant->id,
            'event_type' => 'checkout_created',
            'payment_id' => $payment->id,
            'actor_user_id' => $actorUserId,
            'metadata' => ['order_id' => $order['id']],
        ]);

        return [
            'payment_id' => $payment->id,
            'razorpay_order_id' => $order['id'],
            'razorpay_key' => config('subscription.razorpay_key'),
            'amount' => (float) $quote['total_amount'],
            'razorpay_amount_paise' => Money::toPaise($quote['total_amount']),
            'description' => $description,
            'prefill' => $checkoutMeta['prefill'],
            'notes' => $checkoutMeta['notes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array{notes: array<string, string>, prefill: array<string, string>}
     */
    private function razorpayCheckoutMeta(Tenant $tenant, string $actorUserId, array $quote): array
    {
        $actor = \App\User::query()->find($actorUserId);
        $contact = $this->normalizeIndianMobile($tenant->contact_number);

        $notes = array_filter([
            'company_name' => $tenant->name,
            'mobile' => $tenant->contact_number,
            'gst_number' => $tenant->gst_number,
            'pan_number' => $tenant->pan_number,
            'contact_person' => $tenant->contact_person,
            'address' => filled($tenant->address) ? (string) $tenant->address : null,
            'product' => $this->productDisplayName(),
            'purchase_type' => (string) ($quote['purchase_type'] ?? ''),
            'promo_code' => $this->promoCodeFromQuote($quote),
            'promo_discount' => ((float) ($quote['promo_discount'] ?? 0)) > 0
                ? $this->formatRupee((float) $quote['promo_discount'])
                : null,
            'plan_details' => $this->formatPlanDetails($quote),
            'charges_breakdown' => $this->formatChargesBreakdown($quote),
        ], fn ($value) => filled($value));

        $notes = array_map(
            static fn ($value): string => \Illuminate\Support\Str::limit((string) $value, 256, ''),
            $notes,
        );

        $prefill = array_filter([
            'name' => $tenant->contact_person ?: $tenant->name,
            'email' => $actor?->email,
            'contact' => $contact,
        ], fn ($value) => filled($value));

        return [
            'notes' => $notes,
            'prefill' => array_map(static fn ($value): string => (string) $value, $prefill),
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function razorpayDescription(array $quote): string
    {
        $type = (string) ($quote['purchase_type'] ?? 'subscription');

        return $this->productDisplayName().' '.$type;
    }

    private function productDisplayName(): string
    {
        return match ((string) config('subscription.product')) {
            'broker' => 'TruckBill Broker',
            'ownfleet' => 'TruckBill Ownfleet',
            'parchun' => 'TruckBill Parchun',
            default => 'TruckBill',
        };
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function formatPlanDetails(array $quote): string
    {
        $catalog = is_array($quote['catalog_snapshot'] ?? null) ? $quote['catalog_snapshot'] : [];
        $quantities = is_array($quote['quantities'] ?? null) ? $quote['quantities'] : [];
        $parts = [];

        if (isset($catalog['included_branches']) || isset($quantities['addon_branches'])) {
            $parts[] = 'Branches '.(int) ($catalog['included_branches'] ?? 0).'+'.(int) ($quantities['addon_branches'] ?? 0);
        }
        if (isset($catalog['users_per_branch']) || isset($quantities['addon_users'])) {
            $parts[] = 'Users/branch '.(int) ($catalog['users_per_branch'] ?? 0).'+'.(int) ($quantities['addon_users'] ?? 0).' users';
        }
        if ((int) ($catalog['included_lr_credits'] ?? 0) > 0 || (int) ($quantities['addon_lr_credits'] ?? 0) > 0) {
            $parts[] = 'LR credits '.(int) ($catalog['included_lr_credits'] ?? 0).'+'.(int) ($quantities['addon_lr_credits'] ?? 0);
        }
        if ((int) ($catalog['included_trucks'] ?? 0) > 0 || (int) ($quantities['addon_trucks'] ?? 0) > 0) {
            $parts[] = 'Trucks '.(int) ($catalog['included_trucks'] ?? 0).'+'.(int) ($quantities['addon_trucks'] ?? 0);
        }
        if (! empty($quote['proration']['remaining_days'])) {
            $parts[] = 'Prorated '.$quote['proration']['remaining_days'].' days';
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function formatChargesBreakdown(array $quote): string
    {
        $parts = [];
        $promoCode = $this->promoCodeFromQuote($quote);

        foreach ($quote['line_items'] ?? [] as $item) {
            if (! is_array($item) || ! isset($item['label'])) {
                continue;
            }

            $label = (string) $item['label'];
            if ($promoCode !== null && stripos($label, 'promo') !== false) {
                $label .= ' ('.$promoCode.')';
            }

            $parts[] = $label.': '.$this->formatRupee((float) ($item['amount'] ?? 0));
        }

        if ($promoCode !== null && ((float) ($quote['promo_discount'] ?? 0)) > 0) {
            $hasPromoLine = collect($parts)->contains(
                fn (string $part) => stripos($part, 'promo') !== false
            );
            if (! $hasPromoLine) {
                $parts[] = 'Promo discount ('.$promoCode.'): -'.$this->formatRupee((float) $quote['promo_discount']);
            }
        }

        $gstRate = $quote['gst_rate_percent'] ?? null;
        $gstLabel = $gstRate !== null ? 'GST ('.$gstRate.'%)' : 'GST';
        $parts[] = $gstLabel.': '.$this->formatRupee((float) ($quote['gst_amount'] ?? 0));
        $parts[] = 'Total: '.$this->formatRupee((float) ($quote['total_amount'] ?? 0));

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function promoCodeFromQuote(array $quote): ?string
    {
        $snapshot = $quote['promo_snapshot'] ?? null;
        if (is_array($snapshot) && filled($snapshot['code'] ?? null)) {
            return (string) $snapshot['code'];
        }

        return null;
    }

    private function formatRupee(float $amount): string
    {
        return 'INR '.number_format($amount, 2, '.', '');
    }

    private function normalizeIndianMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?: '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    public function handleCallback(array $payload, string $actorUserId): SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()
            ->where('razorpay_order_id', $payload['razorpay_order_id'])
            ->firstOrFail();

        if ($payment->status === 'completed') {
            return $payment;
        }

        $api = new Api(config('subscription.razorpay_key'), config('subscription.razorpay_secret'));
        $api->utility->verifyPaymentSignature($payload);

        return DB::transaction(function () use ($payment, $payload, $actorUserId) {
            $payment->update([
                'status' => 'completed',
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
                'razorpay_signature' => $payload['razorpay_signature'],
            ]);

            return $this->fulfillCompletedPayment($payment, $actorUserId, 'payment_completed');
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function recordOfflinePurchase(Tenant $tenant, array $input, ?string $actorUserId = null): SubscriptionPayment
    {
        $quoteInput = [
            'purchase_type' => $input['purchase_type'],
            'addon_branches' => (int) ($input['addon_branches'] ?? 0),
            'addon_users' => (int) ($input['addon_users'] ?? 0),
            'addon_trucks' => (int) ($input['addon_trucks'] ?? 0),
            'addon_lr_credits' => 0,
            'promo_code' => null,
            'promo_validated' => false,
        ];

        $quote = $this->pricingService->quote($tenant, $quoteInput);

        if (! ($input['force'] ?? false) && ! $quote['can_checkout']) {
            throw new \InvalidArgumentException(implode(' ', $quote['blocking_errors'] ?: ['Checkout is not available.']));
        }

        return DB::transaction(function () use ($tenant, $input, $quote, $actorUserId) {
            $payment = SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'type' => $input['purchase_type'],
                'status' => 'completed',
                'promo_code' => null,
                'quantities' => $quote['quantities'],
                'subtotal' => $quote['subtotal'],
                'promo_discount' => $quote['promo_discount'],
                'gst_amount' => $quote['gst_amount'],
                'total_amount' => $quote['total_amount'],
                'catalog_snapshot' => $quote['catalog_snapshot'],
                'usage_snapshot_at_purchase' => $quote['current_usage'],
                'razorpay_response' => [
                    'offline' => true,
                    'reference' => $input['reference'] ?? null,
                    'notes' => $input['notes'] ?? null,
                ],
            ]);

            return $this->fulfillCompletedPayment($payment, $actorUserId, 'offline_payment_recorded', [
                'reference' => $input['reference'] ?? null,
                'admin_user_id' => $input['admin_user_id'] ?? null,
            ]);
        });
    }

    private function fulfillCompletedPayment(
        SubscriptionPayment $payment,
        ?string $actorUserId,
        string $eventType,
        array $eventMetadata = [],
    ): SubscriptionPayment {
        $tenant = $payment->tenant;
        $catalog = $payment->catalog_snapshot ?? [];

        $subscription = $tenant->subscription ?? TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'status' => 'expired',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'included_branches' => (int) ($catalog['included_branches'] ?? 0),
            'users_per_branch' => (int) ($catalog['users_per_branch'] ?? 0),
        ]);

        $quantities = $payment->quantities ?? [];

        if ($payment->type === 'addon') {
            $subscription->update([
                'addon_branches' => $subscription->addon_branches + (int) ($quantities['addon_branches'] ?? 0),
                'addon_users' => $subscription->addon_users + (int) ($quantities['addon_users'] ?? 0),
                'addon_trucks' => $subscription->addon_trucks + (int) ($quantities['addon_trucks'] ?? 0),
            ]);
        } else {
            $isActive = $subscription->isActive();

            $snapshot = [
                'included_branches' => (int) $catalog['included_branches'],
                'users_per_branch' => (int) $catalog['users_per_branch'],
                'included_lr_credits' => 0,
                'included_trucks' => (int) ($catalog['included_trucks'] ?? 0),
                'addon_branches' => (int) ($quantities['addon_branches'] ?? 0),
                'addon_users' => (int) ($quantities['addon_users'] ?? 0),
                'addon_trucks' => (int) ($quantities['addon_trucks'] ?? 0),
                'addon_lr_credits' => 0,
            ];

            if ($isActive) {
                UpcomingSubscription::create([
                    'tenant_id' => $tenant->id,
                    'payment_id' => $payment->id,
                    'status' => 'pending',
                    'catalog_snapshot' => $catalog,
                    'promo_snapshot' => $payment->promo_code ? ['code' => $payment->promo_code] : null,
                    ...$snapshot,
                ]);
            } else {
                $term = $this->renewalTermFollowing(
                    $subscription->isActive()
                        ? $subscription->ends_on
                        : now()->subDay()->toDateString(),
                );

                $subscription->update([
                    'status' => 'active',
                    'starts_on' => $term['starts_on'],
                    'ends_on' => $term['ends_on'],
                    'trial_ends_on' => null,
                    'included_branches' => $snapshot['included_branches'],
                    'users_per_branch' => $snapshot['users_per_branch'],
                    'included_lr_credits' => 0,
                    'included_trucks' => $snapshot['included_trucks'],
                    'addon_branches' => $snapshot['addon_branches'],
                    'addon_users' => $snapshot['addon_users'],
                    'addon_trucks' => $snapshot['addon_trucks'],
                    'addon_lr_credits' => 0,
                    'lr_credits_remaining' => 0,
                    'last_renewed_at' => now(),
                ]);
            }
        }

        if ($payment->promo_code) {
            try {
                $this->adminApi->incrementPromoUse($payment->promo_code);
            } catch (\Throwable) {
                // Promo increment failure should not block payment completion.
            }
        }

        SubscriptionEvent::create([
            'tenant_id' => $tenant->id,
            'event_type' => $eventType,
            'payment_id' => $payment->id,
            'actor_user_id' => $actorUserId,
            'metadata' => array_merge(['type' => $payment->type], $eventMetadata),
        ]);

        return $payment->fresh();
    }

    public function applyUpcomingForTenant(string $tenantId): bool
    {
        $today = now()->toDateString();

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($subscription === null || $subscription->ends_on->toDateString() > $today) {
            return false;
        }

        $upcoming = UpcomingSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();

        if ($upcoming === null) {
            if ($subscription->status !== 'expired') {
                $subscription->update(['status' => 'expired']);
            }

            return false;
        }

        $term = $this->renewalTermFollowing($subscription->ends_on);

        $subscription->update([
            'status' => 'active',
            'starts_on' => $term['starts_on'],
            'ends_on' => $term['ends_on'],
            'trial_ends_on' => null,
            'included_branches' => $upcoming->included_branches,
            'users_per_branch' => $upcoming->users_per_branch,
            'included_lr_credits' => 0,
            'included_trucks' => $upcoming->included_trucks,
            'addon_branches' => $upcoming->addon_branches,
            'addon_users' => $upcoming->addon_users,
            'addon_trucks' => $upcoming->addon_trucks,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
            'last_renewed_at' => now(),
        ]);

        $upcoming->update(['status' => 'applied']);

        return true;
    }

    public function applyDueUpcomingSubscriptions(): int
    {
        $applied = 0;
        $today = now()->toDateString();

        $subscriptions = TenantSubscription::query()
            ->where('ends_on', '<=', $today)
            ->whereIn('status', ['active', 'trial', 'expired'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($this->applyUpcomingForTenant($subscription->tenant_id)) {
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * @return array{starts_on: string, ends_on: string}
     */
    public function renewalTermFollowing(Carbon|string $previousEndsOn): array
    {
        $startsOn = Carbon::parse($previousEndsOn)->addDay()->toDateString();
        $endsOn = Carbon::parse($startsOn)->addYear()->subDay()->toDateString();

        return [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ];
    }
}
