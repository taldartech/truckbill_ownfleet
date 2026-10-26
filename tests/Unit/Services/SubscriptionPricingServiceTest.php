<?php

namespace Tests\Unit\Services;

use App\Branch;
use App\Models\TenantSubscription;
use App\Services\AdminApiClient;
use App\Services\SubscriptionPricingService;
use App\Services\SubscriptionUsageService;
use App\Support\BranchProvisioner;
use App\Tenant;
use App\User;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'https://admin.example.com';
    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'subscription.admin_api_url' => self::BASE_URL,
            'subscription.signing_secret' => self::SECRET,
            'subscription.product' => 'ownfleet',
            'subscription.trial_days' => 14,
        ]);
    }

    private function fakeCatalog(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/subscription-catalog*' => Http::response([
                'catalog' => [
                    'base_price' => 5000,
                    'addon_branch_price' => 1000,
                    'addon_user_price' => 500,
                    'addon_truck_price' => 200,
                    'included_branches' => 2,
                    'users_per_branch' => 3,
                    'included_trucks' => 5,
                    'included_lr_credits' => 100,
                ],
                'gst_rate_percent' => 18,
            ], 200),
            self::BASE_URL.'/api/v1/products/ownfleet/promo-codes/validate*' => Http::response([
                'valid' => true,
                'promo' => [
                    'code' => 'FLAT1000',
                    'discount_type' => 'fixed',
                    'discount_value' => 1000,
                ],
            ], 200),
        ]);
    }

    public function test_create_trial_subscription_with_catalog_and_fallback(): void
    {
        $this->fakeCatalog();

        $tenant = Tenant::query()->create([
            'name' => 'Trial Co',
            'slug' => 'trial-co',
            'short_name' => 'TC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $service = app(SubscriptionPricingService::class);
        $subscription = $service->createTrialSubscription($tenant);

        $this->assertSame('trial', $subscription->status);
        $this->assertSame(2, $subscription->included_branches);
        $this->assertSame(3, $subscription->users_per_branch);
        $this->assertSame(5, $subscription->included_trucks);
        $this->assertSame(100, $subscription->lr_credits_remaining);

        // Test fallback when catalog fails
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/subscription-catalog*' => Http::response([], 500),
        ]);

        $tenant2 = Tenant::query()->create([
            'name' => 'Fallback Co',
            'slug' => 'fallback-co',
            'short_name' => 'FC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $subscription2 = $service->createTrialSubscription($tenant2);
        $this->assertSame('trial', $subscription2->status);
        $this->assertSame(2, $subscription2->included_branches);
    }

    public function test_quote_returns_blocking_error_for_addons_on_inactive_subscription(): void
    {
        $this->fakeCatalog();

        $tenant = Tenant::query()->create([
            'name' => 'Inactive Co',
            'slug' => 'inactive-co',
            'short_name' => 'IC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        // No active subscription exists
        $service = app(SubscriptionPricingService::class);
        $quote = $service->quote($tenant, [
            'purchase_type' => 'addon',
            'addon_branches' => 1,
        ]);

        $this->assertFalse($quote['can_checkout']);
        $this->assertContains('Add-ons are only available for active subscriptions.', $quote['blocking_errors']);
    }

    public function test_quote_calculates_fixed_promo_discount(): void
    {
        $this->fakeCatalog();

        $tenant = Tenant::query()->create([
            'name' => 'Promo Co',
            'slug' => 'promo-co',
            'short_name' => 'PC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $service = app(SubscriptionPricingService::class);
        $quote = $service->quote($tenant, [
            'purchase_type' => 'renewal',
            'promo_code' => 'FLAT1000',
            'promo_validated' => true,
        ]);

        $this->assertSame(1000.0, (float) $quote['promo_discount']);
        $this->assertSame(4000.0, (float) ($quote['subtotal'] - $quote['promo_discount']));
    }

    public function test_renewal_capacity_errors_when_usage_exceeds_limits(): void
    {
        $this->fakeCatalog();

        $tenant = Tenant::query()->create([
            'name' => 'Capacity Co',
            'slug' => 'capacity-co',
            'short_name' => 'CC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $branch1 = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Branch 1',
            'branch_contact_number' => '9876543210',
            'is_active' => true,
        ]);
        $branch2 = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Branch 2',
            'branch_contact_number' => '9876543211',
            'is_active' => true,
        ]);
        $branch3 = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Branch 3',
            'branch_contact_number' => '9876543212',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            Vehicle::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch1->id,
                'registration_number' => 'MH04AB000'.$i,
                'vehicle_type' => 'TRUCK',
            ]);
        }

        $service = app(SubscriptionPricingService::class);
        $quote = $service->quote($tenant, [
            'purchase_type' => 'renewal',
            'addon_branches' => 0, // catalog only includes 2, tenant has 3!
            'addon_trucks' => 0,   // catalog only includes 5, tenant has 6!
        ]);

        $this->assertFalse($quote['can_checkout']);
        $this->assertTrue(collect($quote['blocking_errors'])->some(fn ($msg) => str_contains($msg, 'branch add-on')));
        $this->assertTrue(collect($quote['blocking_errors'])->some(fn ($msg) => str_contains($msg, 'vehicle add-on')));
    }
}
