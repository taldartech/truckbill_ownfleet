<?php

namespace App\Support;

use App\Enums\TyrePosition;
use App\Tyre;
use App\TyreInstallation;

class TyreLifeCalculator
{
    public const DEFAULT_EXPECTED_LIFE_KM = 40000;

    public function expectedLifeKm(Tyre $tyre): float
    {
        $value = (float) ($tyre->expected_life_km ?? 0);

        return $value > 0 ? $value : self::DEFAULT_EXPECTED_LIFE_KM;
    }

    /**
     * @return array{
     *     expected_life_km: float,
     *     total_km_used: float,
     *     remaining_life_km: float,
     *     life_used_percent: float,
     *     spare_usage_count: int,
     *     total_repair_cost: float,
     *     total_cost: float,
     *     is_early_wear_risk: bool
     * }
     */
    public function summary(Tyre $tyre): array
    {
        $tyre->loadMissing(['installations.vehicle', 'rotations', 'damages', 'expenses', 'activeInstallation.vehicle']);

        $expected = $this->expectedLifeKm($tyre);
        $totalUsed = (float) $tyre->current_km;

        foreach ($tyre->installations as $installation) {
            if ($installation->running_km !== null) {
                $totalUsed += (float) $installation->running_km;

                continue;
            }

            if ($installation->removed_at === null && $installation->id === $tyre->activeInstallation?->id) {
                $totalUsed += $this->activeStintKm($installation);
            }
        }

        $remaining = max(0, round($expected - $totalUsed, 2));
        $percent = $expected > 0 ? round(min(100, ($totalUsed / $expected) * 100), 1) : 0;

        $spareCount = $this->spareUsageCount($tyre);
        $repairCost = (float) $tyre->damages->sum('repair_cost');
        $expenseCost = (float) $tyre->expenses->sum('amount');
        $totalCost = (float) $tyre->purchase_cost + $expenseCost;

        return [
            'expected_life_km' => $expected,
            'total_km_used' => round($totalUsed, 2),
            'remaining_life_km' => $remaining,
            'life_used_percent' => $percent,
            'spare_usage_count' => $spareCount,
            'total_repair_cost' => round($repairCost, 2),
            'total_cost' => round($totalCost, 2),
            'is_early_wear_risk' => $remaining > ($expected * 0.6) && $percent < 25,
        ];
    }

    public function activeStintKm(TyreInstallation $installation): float
    {
        $vehicleKm = (float) ($installation->vehicle?->odometer_km ?? $installation->km_at_installation);

        return max(0, $vehicleKm - (float) $installation->km_at_installation);
    }

    public function spareUsageCount(Tyre $tyre): int
    {
        $count = 0;

        foreach ($tyre->installations as $installation) {
            if ($this->positionValue($installation->position_code) === TyrePosition::Spare->value) {
                $count++;
            }
        }

        foreach ($tyre->rotations ?? [] as $rotation) {
            if ($this->positionValue($rotation->new_position) === TyrePosition::Spare->value) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{level: string, message: string, remaining_life_km: float, stint_km: float}|null
     */
    public function removalRecommendation(Tyre $tyre, float $stintKm): ?array
    {
        $summary = $this->summary($tyre);
        $expected = $summary['expected_life_km'];
        $remaining = $summary['remaining_life_km'];

        if ($stintKm < 5000 && $remaining > ($expected * 0.5)) {
            return [
                'level' => 'warning',
                'message' => "Tyre removed after only {$stintKm} km on this truck. Expected life {$expected} km — about {$remaining} km may still be usable (check tread before scrap).",
                'remaining_life_km' => $remaining,
                'stint_km' => round($stintKm, 2),
            ];
        }

        if ($remaining <= 5000 && $summary['life_used_percent'] >= 85) {
            return [
                'level' => 'info',
                'message' => "Tyre near end of life (~{$remaining} km left of {$expected} km). Replacement recommended.",
                'remaining_life_km' => $remaining,
                'stint_km' => round($stintKm, 2),
            ];
        }

        return null;
    }

    private function positionValue(mixed $position): ?string
    {
        if ($position instanceof TyrePosition) {
            return $position->value;
        }

        return is_string($position) ? $position : null;
    }
}
