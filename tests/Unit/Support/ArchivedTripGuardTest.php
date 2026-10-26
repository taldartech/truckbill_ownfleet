<?php

namespace Tests\Unit\Support;

use App\Support\ArchivedTripGuard;
use App\Tenant;
use App\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ArchivedTripGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_modifiable_passes_for_active_trip(): void
    {
        $trip = new Trip();
        $trip->deleted_at = null;

        ArchivedTripGuard::ensureModifiable($trip);
        $this->assertTrue(true);
    }

    public function test_ensure_modifiable_aborts_410_for_archived_trip(): void
    {
        $trip = new Trip();
        $trip->deleted_at = now();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('This trip has been archived and cannot be modified.');

        try {
            ArchivedTripGuard::ensureModifiable($trip);
        } catch (HttpException $e) {
            $this->assertSame(410, $e->getStatusCode());
            throw $e;
        }
    }
}
