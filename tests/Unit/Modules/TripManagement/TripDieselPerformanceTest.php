<?php

namespace Tests\Unit\Modules\TripManagement;

use App\Modules\TripManagement\Support\TripDieselPerformance;
use App\TripRouteLeg;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripDieselPerformanceTest extends TestCase
{
    public function test_calculates_over_expected_performance_from_user_example(): void
    {
        $legs = collect([
            $this->leg(2.5),
            $this->leg(3.0),
            $this->leg(2.5),
        ]);

        $result = TripDieselPerformance::calculate($legs, 200, 100, 550);

        $this->assertNotNull($result);
        $this->assertSame(2.67, $result['expected_avg_mileage_km_per_liter']);
        $this->assertSame(534.0, $result['expected_total_km']);
        $this->assertSame(20000.0, $result['total_trip_cost']);
        $this->assertSame(37.45, $result['rate_per_km']);
        $this->assertSame(550.0, $result['actual_total_km']);
        $this->assertSame(16.0, $result['extra_km']);
        $this->assertSame(0.0, $result['unused_km']);
        $this->assertSame('over', $result['performance_status']);
        $this->assertSame(5.99, $result['extra_diesel_liters']);
        $this->assertSame(599.0, $result['extra_diesel_cost']);
    }

    public function test_calculates_remaining_amount_for_unused_booked_km(): void
    {
        $legs = collect([
            $this->leg(2.5),
            $this->leg(3.0),
            $this->leg(2.5),
        ]);

        $result = TripDieselPerformance::calculate($legs, 200, 100, 500);

        $this->assertNotNull($result);
        $this->assertSame(534.0, $result['expected_total_km']);
        $this->assertSame(500.0, $result['actual_total_km']);
        $this->assertSame(34.0, $result['unused_km']);
        $this->assertSame(0.0, $result['extra_km']);
        $this->assertSame(37.45, $result['rate_per_km']);
        $this->assertSame(1273.3, $result['remaining_amount']);
        $this->assertSame('under', $result['performance_status']);
    }

    public function test_returns_null_without_expected_diesel_or_mileage(): void
    {
        $this->assertNull(TripDieselPerformance::calculate(collect([$this->leg(0)]), 200, 100, 500));
        $this->assertNull(TripDieselPerformance::calculate(collect([$this->leg(2.5)]), 0, 100, 500));
    }

    #[DataProvider('withinExpectedProvider')]
    public function test_within_expected_range_has_zero_extra(float $actualKm): void
    {
        $legs = collect([
            $this->leg(2.5),
            $this->leg(3.0),
            $this->leg(2.5),
        ]);

        $result = TripDieselPerformance::calculate($legs, 200, 100, $actualKm);

        $this->assertNotNull($result);
        $this->assertSame(534.0, $result['expected_total_km']);
        $this->assertSame(0.0, $result['extra_km']);
        $this->assertSame(0.0, $result['unused_km']);
        $this->assertSame(0.0, $result['remaining_amount']);
        $this->assertSame('within', $result['performance_status']);
    }

    /**
     * @return array<string, array{float}>
     */
    public static function withinExpectedProvider(): array
    {
        return [
            'exact expected' => [534.0],
        ];
    }

    private function leg(float $mileage): TripRouteLeg
    {
        $leg = new TripRouteLeg;
        $leg->expected_mileage_km_per_liter = $mileage;

        return $leg;
    }
}
