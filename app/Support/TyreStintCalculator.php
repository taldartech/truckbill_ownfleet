<?php

namespace App\Support;

use App\TyreInstallation;
use App\Vehicle;
use Illuminate\Validation\ValidationException;

class TyreStintCalculator
{
    public function resolveInstallOdometer(Vehicle $vehicle, mixed $provided): float
    {
        if ($provided !== null && $provided !== '') {
            return max(0, (float) $provided);
        }

        return max(0, (float) ($vehicle->odometer_km ?? 0));
    }

    public function stintFromOdometer(float $kmAtInstallation, float $currentOdometer): float
    {
        return max(0, round($currentOdometer - max(0, $kmAtInstallation), 2));
    }

    public function stintFromInstallationOdometer(TyreInstallation $installation, float $currentOdometer): float
    {
        $atInstall = max(0, (float) ($installation->km_at_installation ?? 0));
        $currentOdometer = max(0, $currentOdometer);

        if ($currentOdometer < $atInstall) {
            throw ValidationException::withMessages([
                'odometer_km' => "Current meter reading ({$currentOdometer} km) cannot be less than meter reading at installation ({$atInstall} km).",
            ]);
        }

        return $this->stintFromOdometer($atInstall, $currentOdometer);
    }

    /**
     * @param  array{stint_km?: mixed, odometer_km?: mixed, km_at_removal?: mixed}  $data
     */
    public function resolveStintForInstallation(TyreInstallation $installation, array $data): float
    {
        $odometer = $data['odometer_km'] ?? $data['km_at_removal'] ?? null;

        if ($odometer !== null && $odometer !== '') {
            return $this->stintFromInstallationOdometer($installation, (float) $odometer);
        }

        if (! array_key_exists('stint_km', $data) || $data['stint_km'] === null || $data['stint_km'] === '') {
            throw ValidationException::withMessages([
                'odometer_km' => 'Provide current meter reading or KM on truck.',
            ]);
        }

        return max(0, (float) $data['stint_km']);
    }

    /**
     * @param  array{odometer_km?: mixed, km_at_removal?: mixed}  $data
     */
    public function resolveOdometerReading(array $data): ?float
    {
        $odometer = $data['odometer_km'] ?? $data['km_at_removal'] ?? null;

        if ($odometer === null || $odometer === '') {
            return null;
        }

        return max(0, (float) $odometer);
    }

    public function syncVehicleOdometer(Vehicle $vehicle, float $odometerKm): void
    {
        $current = max(0, (float) ($vehicle->odometer_km ?? 0));

        if ($odometerKm > $current) {
            $vehicle->update(['odometer_km' => $odometerKm]);
        }
    }
}
