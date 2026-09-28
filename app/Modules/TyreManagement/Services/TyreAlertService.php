<?php

namespace App\Modules\TyreManagement\Services;

use App\Enums\TyreStatus;
use App\Support\TyreLifeCalculator;
use App\Support\TyrePositionCatalog;
use App\Tyre;
use App\TyreInstallation;
use App\Vehicle;

class TyreAlertService
{
    public function __construct(
        private readonly TyreLifeCalculator $lifeCalculator,
        private readonly TyrePositionCatalog $positionCatalog,
    ) {}

    /**
     * @return array{
     *     warranty_expiring: list<array<string, mixed>>,
     *     warranty_km_exceeded: list<array<string, mixed>>,
     *     near_end_of_life: list<array<string, mixed>>,
     *     inspection_overdue: list<array<string, mixed>>,
     *     vehicles_empty_steer: list<array<string, mixed>>,
     *     counts: array<string, int>
     * }
     */
    public function alerts(): array
    {
        $today = now()->toDateString();
        $warrantyCutoff = now()->addDays(30)->toDateString();

        $warrantyExpiring = Tyre::query()
            ->whereIn('status', [TyreStatus::InStock->value, TyreStatus::Installed->value])
            ->whereNotNull('warranty_valid_until')
            ->whereDate('warranty_valid_until', '>=', $today)
            ->whereDate('warranty_valid_until', '<=', $warrantyCutoff)
            ->orderBy('warranty_valid_until')
            ->limit(25)
            ->get()
            ->map(fn (Tyre $tyre) => $this->tyreAlertRow($tyre, 'warranty_date'))
            ->all();

        $warrantyKmExceeded = Tyre::query()
            ->whereIn('status', [TyreStatus::InStock->value, TyreStatus::Installed->value])
            ->whereNotNull('warranty_km')
            ->where('warranty_km', '>', 0)
            ->get()
            ->filter(function (Tyre $tyre): bool {
                $summary = $this->lifeCalculator->summary($tyre);

                return $summary['total_km_used'] > (float) $tyre->warranty_km;
            })
            ->take(25)
            ->map(fn (Tyre $tyre) => $this->tyreAlertRow($tyre, 'warranty_km'))
            ->values()
            ->all();

        $nearEndOfLife = Tyre::query()
            ->whereIn('status', [TyreStatus::InStock->value, TyreStatus::Installed->value])
            ->get()
            ->filter(function (Tyre $tyre): bool {
                $summary = $this->lifeCalculator->summary($tyre);

                return $summary['remaining_life_km'] <= 5000 || $summary['life_used_percent'] >= 85;
            })
            ->take(25)
            ->map(fn (Tyre $tyre) => $this->tyreAlertRow($tyre, 'end_of_life'))
            ->values()
            ->all();

        $inspectionOverdue = Tyre::query()
            ->whereIn('status', [TyreStatus::InStock->value, TyreStatus::Installed->value])
            ->where(function ($query): void {
                $query->whereNull('last_inspection_at')
                    ->orWhereDate('last_inspection_at', '<', now()->subMonths(3)->toDateString());
            })
            ->orderBy('last_inspection_at')
            ->limit(25)
            ->get()
            ->map(fn (Tyre $tyre) => $this->tyreAlertRow($tyre, 'inspection_overdue'))
            ->all();

        $vehiclesEmptySteer = Vehicle::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->hasEmptySteerPositions($vehicle))
            ->take(25)
            ->map(fn (Vehicle $vehicle) => [
                'vehicle_id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'empty_steer_positions' => $this->emptySteerPositionLabels($vehicle),
            ])
            ->values()
            ->all();

        return [
            'warranty_expiring' => $warrantyExpiring,
            'warranty_km_exceeded' => $warrantyKmExceeded,
            'near_end_of_life' => $nearEndOfLife,
            'inspection_overdue' => $inspectionOverdue,
            'vehicles_empty_steer' => $vehiclesEmptySteer,
            'counts' => [
                'warranty_expiring' => count($warrantyExpiring),
                'warranty_km_exceeded' => count($warrantyKmExceeded),
                'near_end_of_life' => count($nearEndOfLife),
                'inspection_overdue' => count($inspectionOverdue),
                'vehicles_empty_steer' => count($vehiclesEmptySteer),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function dashboardCounts(): array
    {
        $alerts = $this->alerts();

        return $alerts['counts'];
    }

    private function tyreAlertRow(Tyre $tyre, string $reason): array
    {
        $summary = $this->lifeCalculator->summary($tyre);

        return [
            'tyre_id' => $tyre->id,
            'serial_number' => $tyre->serial_number,
            'brand' => $tyre->brand,
            'status' => $tyre->status?->value,
            'reason' => $reason,
            'warranty_valid_until' => $tyre->warranty_valid_until?->toDateString(),
            'warranty_km' => $tyre->warranty_km !== null ? (float) $tyre->warranty_km : null,
            'remaining_life_km' => $summary['remaining_life_km'],
            'life_used_percent' => $summary['life_used_percent'],
            'last_inspection_at' => $tyre->last_inspection_at?->toDateString(),
        ];
    }

    private function hasEmptySteerPositions(Vehicle $vehicle): bool
    {
        return $this->emptySteerPositionLabels($vehicle) !== [];
    }

    /**
     * @return list<string>
     */
    private function emptySteerPositionLabels(Vehicle $vehicle): array
    {
        $installedCodes = TyreInstallation::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('removed_at')
            ->pluck('position_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        return collect($this->positionCatalog->positionsForVehicle($vehicle))
            ->filter(function (array $slot) use ($installedCodes): bool {
                if (($slot['is_spare'] ?? false) === true) {
                    return false;
                }

                $code = (string) ($slot['code'] ?? '');
                $isSteer = str_starts_with($code, 'STEER')
                    || in_array($code, ['FRONT_LEFT', 'FRONT_RIGHT'], true);

                return $isSteer && ! in_array($code, $installedCodes, true);
            })
            ->map(fn (array $slot) => (string) ($slot['label'] ?? $slot['code']))
            ->values()
            ->all();
    }
}
