<?php

namespace Tests\Unit\Support;

use App\Support\SubscriptionExpiredResponse;
use App\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Tests\TestCase;

class SubscriptionExpiredResponseTest extends TestCase
{
    public function test_redirects_for_grace_and_locked_status(): void
    {
        $request = Request::create('/create', 'POST');

        $graceResponse = SubscriptionExpiredResponse::forGraceRestrictedAction($request);
        $this->assertSame(route('app.dashboard'), $graceResponse->getTargetUrl());

        $lockedResponse = SubscriptionExpiredResponse::forLocked($request);
        $this->assertSame(route('app.dashboard'), $lockedResponse->getTargetUrl());
    }

    public function test_abort_unless_can_mutate_throws_for_grace_mode(): void
    {
        $request = Request::create('/trips', 'POST');
        $tenant = $this->createMock(Tenant::class);
        $tenant->method('subscriptionAccessPayload')
            ->willReturn(['access_mode' => 'grace']);

        $this->expectException(HttpResponseException::class);
        SubscriptionExpiredResponse::abortUnlessCanMutate($request, $tenant);
    }

    public function test_abort_unless_can_mutate_throws_for_locked_mode_and_null_tenant(): void
    {
        $request = Request::create('/trips', 'POST');

        try {
            SubscriptionExpiredResponse::abortUnlessCanMutate($request, null);
            $this->fail('Expected HttpResponseException for null tenant');
        } catch (HttpResponseException $e) {
            $this->assertSame(route('app.dashboard'), $e->getResponse()->getTargetUrl());
        }

        $tenant = $this->createMock(Tenant::class);
        $tenant->method('subscriptionAccessPayload')
            ->willReturn(['access_mode' => 'locked']);

        $this->expectException(HttpResponseException::class);
        SubscriptionExpiredResponse::abortUnlessCanMutate($request, $tenant);
    }
}
