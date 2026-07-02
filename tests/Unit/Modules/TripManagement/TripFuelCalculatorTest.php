<?php

namespace Tests\Unit\Modules\TripManagement;

use App\Modules\TripManagement\Support\TripFuelCalculator;
use App\Trip;
use Tests\TestCase;

class TripFuelCalculatorTest extends TestCase
{
    public function test_calculates_planned_diesel_from_running_km_and_mileage(): void
    {
        $this->assertSame(200.0, TripFuelCalculator::calculatePlannedDiesel(500, 2.5));
    }

    public function test_calculates_expected_running_km_from_meters(): void
    {
        $this->assertSame(500.0, TripFuelCalculator::calculateExpectedRunningKm(1000, 1500));
        $this->assertNull(TripFuelCalculator::calculateExpectedRunningKm(null, 1500));
    }

    public function test_calculates_performance_summary_from_trip_snapshot(): void
    {
        $trip = new Trip([
            'planned_mileage_km_per_liter' => 2.67,
            'planned_diesel_liters' => 200,
            'diesel_rate_per_liter' => 100,
            'total_distance_km' => 550,
        ]);

        $result = TripFuelCalculator::calculatePerformanceSummary($trip);

        $this->assertNotNull($result);
        $this->assertSame(TripFuelCalculator::CALCULATION_VERSION, $result['calculation_version']);
        $this->assertSame(534.0, $result['expected_total_km']);
        $this->assertSame(550.0, $result['actual_total_km']);
        $this->assertSame('over', $result['performance_status']);
    }
}
