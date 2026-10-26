<?php

namespace Tests\Feature;

use App\Models\SubscriptionPayment;
use App\Models\TenantSubscription;
use App\Tenant;
use App\User;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_SECRET = 'test-signing-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'onboarding.signing_secret' => self::SIGNING_SECRET,
            'subscription.signing_secret' => self::SIGNING_SECRET,
            'subscription.admin_api_url' => 'https://admin.example.com',
            'subscription.product' => 'ownfleet',
        ]);
    }

    private function signedRequest(string $method, string $uri, array $data = []): TestResponse
    {
        config(['onboarding.signing_secret' => self::SIGNING_SECRET]);
        $timestamp = (string) time();
        $content = $data !== [] ? json_encode($data) : '';
        $signature = hash_hmac('sha256', $timestamp.'.'.$content, self::SIGNING_SECRET);

        $headers = [
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ];

        if ($method === 'GET') {
            return $this->withHeaders($headers)->get($uri);
        }

        return $this->withHeaders($headers)->json($method, $uri, $data);
    }

    private function fakeCatalogApi(): void
    {
        Http::fake([
            'https://admin.example.com/api/v1/products/ownfleet/subscription-catalog*' => Http::response([
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
            'https://admin.example.com/api/v1/products/ownfleet/promo-codes/validate*' => Http::response([
                'valid' => true,
                'promo' => [
                    'code' => 'SAVE10',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                ],
            ], 200),
            'https://admin.example.com/api/v1/products/ownfleet/promo-codes/increment-use*' => Http::response([], 200),
        ]);
    }

    public function test_signature_verification_security_checks(): void
    {
        // Missing signature headers
        $this->getJson('/api/admin/subscriptions')->assertStatus(401);

        // Invalid timestamp
        $this->withHeaders([
            'X-Timestamp' => 'not-a-number',
            'X-Signature' => 'sig',
        ])->getJson('/api/admin/subscriptions')->assertStatus(401);

        // Expired timestamp
        $this->withHeaders([
            'X-Timestamp' => (string) (time() - 400),
            'X-Signature' => 'sig',
        ])->getJson('/api/admin/subscriptions')->assertStatus(401);

        // Bad signature
        $this->withHeaders([
            'X-Timestamp' => (string) time(),
            'X-Signature' => 'wrong-signature',
        ])->getJson('/api/admin/subscriptions')->assertStatus(401);

        // Missing secret configuration
        config(['onboarding.signing_secret' => null]);
        $this->withHeaders([
            'X-Timestamp' => (string) time(),
            'X-Signature' => 'sig',
        ])->getJson('/api/admin/subscriptions')->assertStatus(503);
    }

    public function test_index_and_search_returns_tenants_with_search_and_pagination(): void
    {
        $tenant1 = Tenant::query()->create([
            'name' => 'Alpha Logistics',
            'slug' => 'alpha-logistics',
            'short_name' => 'AL',
            'contact_person' => 'John Alpha',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $tenant2 = Tenant::query()->create([
            'name' => 'Beta Freight',
            'slug' => 'beta-freight',
            'short_name' => 'BF',
            'contact_person' => 'Bob Beta',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenant1->id,
            'status' => 'active',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        $response = $this->signedRequest('GET', '/api/admin/subscriptions?per_page=10');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        // Search by name
        $searchResponse = $this->signedRequest('GET', '/api/admin/subscriptions/search?q=Alpha');
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertSame('Alpha Logistics', $searchResponse->json('data.0.name'));
        $this->assertSame('active', $searchResponse->json('data.0.status'));
    }

    public function test_show_returns_full_subscription_and_usage_details(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Gamma Shipping',
            'slug' => 'gamma-shipping',
            'short_name' => 'GS',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->addDays(20)->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'type' => 'renewal',
            'status' => 'completed',
            'total_amount' => 5900,
            'subtotal' => 5000,
            'gst_amount' => 900,
        ]);

        $response = $this->signedRequest('GET', "/api/admin/subscriptions/{$tenant->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'company' => ['id', 'name'],
            'subscription',
            'usage',
            'payments',
            'upcoming',
        ]);
        $this->assertSame('Gamma Shipping', $response->json('company.name'));
        $this->assertCount(1, $response->json('payments'));
    }

    public function test_quote_calculates_renewal_and_addon_pricing(): void
    {
        $this->fakeCatalogApi();

        $tenant = Tenant::query()->create([
            'name' => 'Delta Transport',
            'slug' => 'delta-transport',
            'short_name' => 'DT',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(365)->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        // Quote for renewal
        $renewalQuote = $this->signedRequest('POST', "/api/admin/subscriptions/{$tenant->id}/quote", [
            'purchase_type' => 'renewal',
            'addon_branches' => 1,
            'addon_users' => 2,
            'addon_trucks' => 10,
            'promo_code' => 'SAVE10',
        ]);

        $renewalQuote->assertOk();
        $this->assertTrue($renewalQuote->json('can_checkout'));
        $this->assertGreaterThan(0, $renewalQuote->json('total_amount'));
        $this->assertNotNull($renewalQuote->json('promo_snapshot'));

        // Quote for addon on active subscription
        $addonQuote = $this->signedRequest('POST', "/api/admin/subscriptions/{$tenant->id}/quote", [
            'purchase_type' => 'addon',
            'addon_branches' => 1,
            'addon_users' => 1,
        ]);

        $addonQuote->assertOk();
        $this->assertTrue($addonQuote->json('can_checkout'));
        $this->assertGreaterThan(0, $addonQuote->json('total_amount'));
    }

    public function test_record_offline_payment_creates_and_fulfills_payment(): void
    {
        $this->fakeCatalogApi();

        $tenant = Tenant::query()->create([
            'name' => 'Epsilon Cargo',
            'slug' => 'epsilon-cargo',
            'short_name' => 'EC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $response = $this->signedRequest('POST', "/api/admin/subscriptions/{$tenant->id}/offline-payment", [
            'purchase_type' => 'renewal',
            'addon_branches' => 1,
            'addon_users' => 2,
            'addon_trucks' => 5,
            'reference' => 'CHQ-998877',
            'notes' => 'Received cheque payment at branch',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => $tenant->id,
            'status' => 'completed',
            'type' => 'renewal',
        ]);

        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertSame(1, $subscription->addon_branches);
        $this->assertSame(2, $subscription->addon_users);
        $this->assertSame(5, $subscription->addon_trucks);
    }

    public function test_update_modifies_subscription_and_validates_changes(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Zeta Logistics',
            'slug' => 'zeta-logistics',
            'short_name' => 'ZL',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'status' => 'trial',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(15)->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        // Attempting update with identical attributes returns 422
        $noChangeResponse = $this->signedRequest('PATCH', "/api/admin/subscriptions/{$tenant->id}", [
            'status' => 'trial',
        ]);
        $noChangeResponse->assertStatus(422)
            ->assertJson(['message' => 'No changes to save.']);

        // Updating subscription attributes
        $newEnd = now()->addYear()->toDateString();
        $updateResponse = $this->signedRequest('PATCH', "/api/admin/subscriptions/{$tenant->id}", [
            'status' => 'active',
            'ends_on' => $newEnd,
            'addon_branches' => 3,
            'notes' => 'Extended trial and upgraded to active manually',
        ]);

        $updateResponse->assertOk();
        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($newEnd, $subscription->ends_on->toDateString());
        $this->assertSame(3, $subscription->addon_branches);

        $this->assertDatabaseHas('subscription_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'admin_override',
        ]);
    }
}
