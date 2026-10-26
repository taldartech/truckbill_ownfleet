<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPayment;
use App\Models\TenantSubscription;
use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionManageTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_URL = 'https://admin.example.com';
    private const SECRET = 'test-secret';
    private const RAZORPAY_SECRET = 'rzp_test_secret_123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        config([
            'subscription.admin_api_url' => self::ADMIN_URL,
            'subscription.signing_secret' => self::SECRET,
            'subscription.product' => 'ownfleet',
            'subscription.razorpay_key' => 'rzp_test_key',
            'subscription.razorpay_secret' => self::RAZORPAY_SECRET,
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
                    'code' => 'SUPER10',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                ],
            ], 200),
        ]);
    }

    public function test_manage_page_counts_vehicles_without_tenant_context(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Usage Co');

        TyreLayoutDefaults::seedForTenant($tenant->id);
        TruckTypeDefaults::seedForTenant($tenant->id);

        foreach (['MH01AA1111', 'MH01BB2222', 'MH01CC3333'] as $registration) {
            Vehicle::query()->withoutGlobalScopes(['tenant', 'branch'])->create([
                ...$this->operationalAttributesForTenant($tenant),
                'registration_number' => $registration,
                'vehicle_type' => 'TRUCK',
                'make' => 'Tata',
                'model' => 'Ultra',
                'odometer_km' => 1000,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription/manage?company_id='.$tenant->id)
            ->assertOk();

        $vehicleMetric = collect($response->json('usageMetrics'))
            ->firstWhere('label', 'Vehicles');

        $this->assertSame(3, $vehicleMetric['used']);
        $this->assertSame(50, $vehicleMetric['max']);
    }

    public function test_index_returns_companies_and_subscription_summary(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant1 = $this->createOwnedTenant($superAdmin, 'Active Co');
        $tenant2 = $this->createOwnedTenant($superAdmin, 'Expired Co');
        $tenant2->subscription->update([
            'status' => 'expired',
            'ends_on' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription')
            ->assertOk();

        $this->assertSame(2, $response->json('summary.total'));
        $this->assertSame(1, $response->json('summary.active'));
        $this->assertSame(1, $response->json('summary.expired'));
    }

    public function test_checkout_page_returns_pricing_and_enforces_rules(): void
    {
        $this->fakeCatalog();
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Checkout Co');

        // Renewal checkout page
        $renewalResp = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription/checkout?company_id='.$tenant->id.'&purchase_type=renewal');
        $renewalResp->assertOk()
            ->assertJsonPath('purchaseType', 'renewal')
            ->assertJsonPath('isExpired', false);

        // Addon checkout page when active
        $addonResp = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription/checkout?company_id='.$tenant->id.'&purchase_type=addon');
        $addonResp->assertOk()
            ->assertJsonPath('purchaseType', 'addon');

        // Addon checkout page when expired returns 422
        $tenant->subscription->update(['status' => 'expired', 'ends_on' => now()->subDay()->toDateString()]);
        $expiredAddonResp = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription/checkout?company_id='.$tenant->id.'&purchase_type=addon');
        $expiredAddonResp->assertStatus(422)
            ->assertJsonPath('message', 'Add-ons are only available for active subscriptions.');
    }

    public function test_quote_and_validate_promo_endpoints(): void
    {
        $this->fakeCatalog();
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Quote Co');

        // Validate promo code
        $promoResp = $this->actingAs($superAdmin)
            ->postJson('/app/api/subscription/promo/validate', [
                'code' => 'SUPER10',
            ]);
        $promoResp->assertOk()
            ->assertJsonPath('valid', true);

        // Quote
        $quoteResp = $this->actingAs($superAdmin)
            ->postJson('/app/api/subscription/quote', [
                'company_id' => $tenant->id,
                'purchase_type' => 'renewal',
                'addon_branches' => 1,
                'addon_users' => 2,
                'promo_code' => 'SUPER10',
                'promo_validated' => true,
            ]);
        $quoteResp->assertOk()
            ->assertJsonPath('can_checkout', true);
        $this->assertGreaterThan(0, $quoteResp->json('total_amount'));
    }

    public function test_payments_history_endpoint(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'History Co');

        SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'type' => 'renewal',
            'status' => 'completed',
            'total_amount' => 5900,
            'subtotal' => 5000,
            'gst_amount' => 900,
        ]);

        $response = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription/payments?company_id='.$tenant->id);
        $response->assertOk();
        $this->assertCount(1, $response->json('payments.data'));
        $this->assertSame(5900.0, (float) $response->json('totalPaid'));
    }

    public function test_callback_completes_payment_with_valid_signature(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Callback Co');

        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'type' => 'addon',
            'status' => 'pending',
            'razorpay_order_id' => 'order_xyz_123',
            'quantities' => ['addon_branches' => 2],
            'subtotal' => 2000,
            'gst_amount' => 360,
            'total_amount' => 2360,
        ]);

        $paymentId = 'pay_abc_456';
        $orderId = 'order_xyz_123';
        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, self::RAZORPAY_SECRET);

        $response = $this->actingAs($superAdmin)
            ->postJson('/app/api/subscription/callback', [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame($paymentId, $payment->razorpay_payment_id);
    }

    public function test_non_super_admin_cannot_access_subscription_endpoints(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Protected Co');

        $regularUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Regular Admin',
            'email' => 'regular@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $this->actingAs($regularUser)
            ->getJson('/app/api/subscription')
            ->assertForbidden();

        $this->actingAs($regularUser)
            ->getJson('/app/api/subscription/manage?company_id='.$tenant->id)
            ->assertForbidden();
    }

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    private function createOwnedTenant(User $owner, string $name): Tenant
    {
        $tenant = Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        return $tenant;
    }
}
