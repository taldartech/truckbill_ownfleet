<?php

namespace Tests\Unit\Services;

use App\Exceptions\SubscriptionCatalogUnavailableException;
use App\Services\AdminApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminApiClientTest extends TestCase
{
    private const BASE_URL = 'https://admin.example.com';
    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'subscription.admin_api_url' => self::BASE_URL,
            'subscription.signing_secret' => self::SECRET,
            'subscription.product' => 'ownfleet',
        ]);
    }

    public function test_get_catalog_returns_payload_on_success(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/subscription-catalog' => Http::response([
                'catalog' => [
                    'base_price' => 5000,
                    'included_branches' => 2,
                ],
                'gst_rate_percent' => 18,
            ], 200),
        ]);

        $client = app(AdminApiClient::class);
        $result = $client->getCatalog();

        $this->assertIsArray($result['catalog']);
        $this->assertSame(5000, $result['catalog']['base_price']);
    }

    public function test_get_catalog_throws_when_catalog_empty_or_malformed(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/subscription-catalog' => Http::response([
                'catalog' => null,
            ], 200),
        ]);

        $this->expectException(SubscriptionCatalogUnavailableException::class);
        $client = app(AdminApiClient::class);
        $client->getCatalog();
    }

    public function test_get_catalog_throws_when_server_returns_error(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/subscription-catalog' => Http::response([], 500),
        ]);

        $this->expectException(SubscriptionCatalogUnavailableException::class);
        $client = app(AdminApiClient::class);
        $client->getCatalog();
    }

    public function test_get_catalog_throws_when_connection_fails(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/subscription-catalog' => fn () => throw new \Exception('Network error'),
        ]);

        $this->expectException(SubscriptionCatalogUnavailableException::class);
        $client = app(AdminApiClient::class);
        $client->getCatalog();
    }

    public function test_request_throws_when_config_is_empty(): void
    {
        config(['subscription.admin_api_url' => '']);

        $this->expectException(SubscriptionCatalogUnavailableException::class);
        $client = app(AdminApiClient::class);
        $client->getCatalog();
    }

    public function test_validate_promo_and_increment_promo_use(): void
    {
        Http::fake([
            self::BASE_URL.'/api/v1/products/ownfleet/promo-codes/validate' => Http::response([
                'valid' => true,
                'promo' => ['code' => 'FLAT500', 'discount_type' => 'fixed', 'discount_value' => 500],
            ], 200),
            self::BASE_URL.'/api/v1/products/ownfleet/promo-codes/increment-use' => Http::response([], 200),
        ]);

        $client = app(AdminApiClient::class);
        $validation = $client->validatePromo('FLAT500');
        $this->assertTrue($validation['valid']);

        $client->incrementPromoUse('FLAT500');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'promo-codes/increment-use'));
    }
}
