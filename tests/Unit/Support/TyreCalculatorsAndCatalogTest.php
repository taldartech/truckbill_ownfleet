<?php

namespace Tests\Unit\Support;

use App\Support\TyreLifeCalculator;
use App\Support\TyrePositionCatalog;
use App\Support\TyreStintCalculator;
use App\Tyre;
use App\TyreInstallation;
use App\TyreLayout;
use App\TyreRotation;
use App\Vehicle;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TyreCalculatorsAndCatalogTest extends TestCase
{
    use RefreshDatabase;
    public function test_tyre_stint_calculator_resolve_install_odometer(): void
    {
        $calc = new TyreStintCalculator();
        $vehicle = new Vehicle(['odometer_km' => 15000]);

        $this->assertSame(10000.0, $calc->resolveInstallOdometer($vehicle, 10000));
        $this->assertSame(15000.0, $calc->resolveInstallOdometer($vehicle, null));
        $this->assertSame(15000.0, $calc->resolveInstallOdometer($vehicle, ''));
        $this->assertSame(0.0, $calc->resolveInstallOdometer($vehicle, -500));
    }

    public function test_tyre_stint_calculator_stint_from_odometer(): void
    {
        $calc = new TyreStintCalculator();
        $this->assertSame(2500.0, $calc->stintFromOdometer(10000, 12500));
        $this->assertSame(0.0, $calc->stintFromOdometer(12500, 10000));
    }

    public function test_tyre_stint_calculator_from_installation_odometer(): void
    {
        $calc = new TyreStintCalculator();
        $installation = new TyreInstallation(['km_at_installation' => 10000]);

        $this->assertSame(2000.0, $calc->stintFromInstallationOdometer($installation, 12000));

        $this->expectException(ValidationException::class);
        $calc->stintFromInstallationOdometer($installation, 9000);
    }

    public function test_tyre_stint_calculator_resolve_stint_for_installation(): void
    {
        $calc = new TyreStintCalculator();
        $installation = new TyreInstallation(['km_at_installation' => 10000]);

        // With odometer_km
        $this->assertSame(3000.0, $calc->resolveStintForInstallation($installation, ['odometer_km' => 13000]));

        // With km_at_removal
        $this->assertSame(4000.0, $calc->resolveStintForInstallation($installation, ['km_at_removal' => 14000]));

        // With stint_km
        $this->assertSame(2500.0, $calc->resolveStintForInstallation($installation, ['stint_km' => 2500]));

        // Exception when missing
        $this->expectException(ValidationException::class);
        $calc->resolveStintForInstallation($installation, []);
    }

    public function test_tyre_stint_calculator_resolve_odometer_reading(): void
    {
        $calc = new TyreStintCalculator();
        $this->assertSame(15000.0, $calc->resolveOdometerReading(['odometer_km' => 15000]));
        $this->assertSame(16000.0, $calc->resolveOdometerReading(['km_at_removal' => 16000]));
        $this->assertNull($calc->resolveOdometerReading([]));
        $this->assertNull($calc->resolveOdometerReading(['odometer_km' => '']));
    }

    public function test_tyre_position_catalog_spare_and_steer_checks(): void
    {
        $catalog = new TyrePositionCatalog();

        $this->assertFalse($catalog->isSpare(null));
        $this->assertFalse($catalog->isSpare(''));
        $this->assertTrue($catalog->isSpare('SPARE_1'));
        $this->assertTrue($catalog->isSpare('SPARE'));
        $this->assertFalse($catalog->isSpare('FL'));

        $this->assertFalse($catalog->isSteerPosition(null));
        $this->assertFalse($catalog->isSteerPosition(''));
        $this->assertTrue($catalog->isSteerPosition('STEER_L'));
        $this->assertTrue($catalog->isSteerPosition('FRONT_LEFT'));
        $this->assertTrue($catalog->isSteerPosition('FRONT_RIGHT'));
        $this->assertFalse($catalog->isSteerPosition('AX1_L_OUT'));
    }

    public function test_tyre_position_catalog_labels(): void
    {
        $catalog = new TyrePositionCatalog();

        $this->assertSame('—', $catalog->labelFor(null));
        $this->assertSame('—', $catalog->labelFor(''));
        $this->assertNotEmpty($catalog->labelFor('FL'));
        $this->assertSame('custom pos', $catalog->labelFor('CUSTOM_POS'));
    }

    public function test_tyre_position_catalog_build_layout_and_positions(): void
    {
        $catalog = new TyrePositionCatalog();

        // Test with a mock custom TyreLayout
        $layout = new TyreLayout([
            'code' => 'CUSTOM_10',
            'name' => '10 Wheeler',
            'steer_axles' => 2,
            'dual_axles' => [2, 3],
            'single_axles' => [4],
            'spare_count' => 2,
        ]);

        $positions = $catalog->positionsForTyreLayout($layout);
        $this->assertNotEmpty($positions);

        // Verify steer slots
        $codes = collect($positions)->pluck('code')->all();
        $this->assertContains('STEER_L', $codes);
        $this->assertContains('STEER_R', $codes);
        $this->assertContains('STEER2_L', $codes);
        $this->assertContains('STEER2_R', $codes);
        $this->assertContains('AX2_L_OUT', $codes);
        $this->assertContains('AX4_L', $codes);
        $this->assertContains('SPARE_1', $codes);
        $this->assertContains('SPARE_2', $codes);
    }

    public function test_tyre_life_calculator_expected_life(): void
    {
        $calc = new TyreLifeCalculator();

        $tyreWithExpected = new Tyre(['expected_life_km' => 60000]);
        $this->assertSame(60000.0, $calc->expectedLifeKm($tyreWithExpected));

        $tyreWithoutExpected = new Tyre(['expected_life_km' => 0]);
        $this->assertSame((float) TyreLifeCalculator::DEFAULT_EXPECTED_LIFE_KM, $calc->expectedLifeKm($tyreWithoutExpected));
    }

    public function test_tyre_life_calculator_recommendations(): void
    {
        $calc = new TyreLifeCalculator();

        // 1. Warning when removed too early with high life remaining
        $tyre1 = new Tyre([
            'expected_life_km' => 50000,
            'current_km' => 1000,
            'purchase_cost' => 15000,
        ]);
        $tyre1->setRelation('installations', collect());
        $tyre1->setRelation('rotations', collect());
        $tyre1->setRelation('damages', collect());
        $tyre1->setRelation('expenses', collect());

        $recEarly = $calc->removalRecommendation($tyre1, 1000);
        $this->assertNotNull($recEarly);
        $this->assertSame('warning', $recEarly['level']);

        // 2. Info when near end of life
        $tyre2 = new Tyre([
            'expected_life_km' => 50000,
            'current_km' => 46000,
            'purchase_cost' => 15000,
        ]);
        $tyre2->setRelation('installations', collect());
        $tyre2->setRelation('rotations', collect());
        $tyre2->setRelation('damages', collect());
        $tyre2->setRelation('expenses', collect());

        $recEnd = $calc->removalRecommendation($tyre2, 2000);
        $this->assertNotNull($recEnd);
        $this->assertSame('info', $recEnd['level']);

        // 3. Normal removal returns null
        $recNormal = $calc->removalRecommendation($tyre1, 20000);
        $this->assertNull($recNormal);
    }

    public function test_tyre_life_calculator_active_stint_km(): void
    {
        $calc = new TyreLifeCalculator();

        // Removed installation returns stored stint_km
        $instRemoved = new TyreInstallation(['stint_km' => 1200, 'removed_at' => now()]);
        $this->assertSame(1200.0, $calc->activeStintKm($instRemoved));

        // Installation without vehicle returns stored stint_km
        $instNoVehicle = new TyreInstallation(['stint_km' => 800, 'removed_at' => null]);
        $instNoVehicle->setRelation('vehicle', null);
        $this->assertSame(800.0, $calc->activeStintKm($instNoVehicle));

        // Active installation with vehicle
        $vehicle = new Vehicle(['odometer_km' => 20000]);
        $instActive = new TyreInstallation([
            'stint_km' => 500,
            'km_at_installation' => 18000,
            'removed_at' => null,
        ]);
        $instActive->setRelation('vehicle', $vehicle);

        // Distance = 20000 - 18000 = 2000
        $this->assertSame(2000.0, $calc->activeStintKm($instActive));
    }
}
