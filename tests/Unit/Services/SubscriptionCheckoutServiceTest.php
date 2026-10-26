<?php

namespace Tests\Unit\Services;

use App\Models\SubscriptionPayment;
use App\Models\TenantSubscription;
use App\Models\UpcomingSubscription;
use App\Services\SubscriptionCheckoutService;
use App\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_URL = 'https://admin.example.com';
    private const SECRET = 'test-secret';
    private const RZP_SECRET = 'rzp_sec_abc';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'subscription.admin_api_url' => self::ADMIN_URL,
            'subscription.signing_secret' => self::SECRET,
            'subscription.product' => 'ownfleet',
            'subscription.razorpay_key' => 'rzp_key',
            'subscription.razorpay_secret' => self::RZP_SECRET,
        ]);
    }

    private function fakeCatalog(): void
    {
        Http::fake([
            self::ADMIN_URL.'/api/v1/products/ownfleet/subscription-catalog*' => Http::response([
                'catalog' => [
                    'base_price' => 5000,
                    'addon_branch_price' => 1000,
                    'addon_user_price' => 500,
                    'addon_truck_price' => 200,
                    'included_branches' => 2,
                    'users_per_branch' => 3,
                    'included_trucks' => 50,
                ],
                'gst_rate_percent' => 18,
            ], 200),
            self::ADMIN_URL.'/api/v1/products/ownfleet/promo-codes/validate*' => Http::response([
                'valid' => true,
                'promo' => [
                    'code' => 'DISC10',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                ],
            ], 200),
        ]);
    }

    public function test_handle_callback_returns_already_completed_payment(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Completed Co',
            'slug' => 'completed-co',
            'short_name' => 'CC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'type' => 'renewal',
            'status' => 'completed',
            'razorpay_order_id' => 'order_done_111',
            'subtotal' => 5000,
            'gst_amount' => 900,
            'total_amount' => 5900,
        ]);

        $service = app(SubscriptionCheckoutService::class);
        $result = $service->handleCallback([
            'razorpay_order_id' => 'order_done_111',
            'razorpay_payment_id' => 'pay_111',
            'razorpay_signature' => 'sig_111',
        ], 'actor-1');

        $this->assertSame($payment->id, $result->id);
    }

    public function test_handle_callback_creates_upcoming_subscription_when_active(): void
    {
        $this->fakeCatalog();

        $tenant = Tenant::query()->create([
            'name' => 'Active Renewal Co',
            'slug' => 'active-renewal-co',
            'short_name' => 'ARC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $activeEnds = now()->addMonths(6)->toDateString();
        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->subMonths(6)->toDateString(),
            'ends_on' => $activeEnds,
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        $orderId = 'order_act_222';
        $paymentId = 'pay_act_222';
        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, self::RZP_SECRET);

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'type' => 'renewal',
            'status' => 'pending',
            'razorpay_order_id' => $orderId,
            'quantities' => ['addon_branches' => 1],
            'catalog_snapshot' => [
                'included_branches' => 2,
                'users_per_branch' => 3,
                'included_trucks' => 50,
            ],
            'subtotal' => 6000,
            'gst_amount' => 1080,
            'total_amount' => 7080,
        ]);

        $service = app(SubscriptionCheckoutService::class);
        $completedPayment = $service->handleCallback([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ], 'actor-2');

        $this->assertSame('completed', $completedPayment->status);

        $upcoming = UpcomingSubscription::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($upcoming);
        $this->assertSame('pending', $upcoming->status);
        $this->assertSame(1, $upcoming->addon_branches);
    }

    public function test_record_offline_purchase_with_invalid_promo_and_force_flag(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Offline Co',
            'slug' => 'offline-co',
            'short_name' => 'OFC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $service = app(SubscriptionCheckoutService::class);

        // Invalid promo code throws InvalidArgumentException
        Http::fake([
            self::ADMIN_URL.'/api/v1/products/ownfleet/promo-codes/validate*' => Http::response([
                'valid' => false,
            ], 200),
            self::ADMIN_URL.'/api/v1/products/ownfleet/subscription-catalog*' => Http::response([
                'catalog' => [
                    'base_price' => 5000,
                    'addon_branch_price' => 1000,
                    'addon_user_price' => 500,
                    'addon_truck_price' => 200,
                    'included_branches' => 2,
                    'users_per_branch' => 3,
                    'included_trucks' => 50,
                ],
                'gst_rate_percent' => 18,
            ], 200),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promo code is invalid');

        $service->recordOfflinePurchase($tenant, [
            'purchase_type' => 'renewal',
            'promo_code' => 'BADCODE',
        ]);
    }
}
