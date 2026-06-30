<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_tenants_with_valid_signature(): void
    {
        config(['onboarding.signing_secret' => 'test-secret']);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.', 'test-secret');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ])->get('/api/admin/subscriptions?per_page=50');

        $response->assertOk();
    }
}
