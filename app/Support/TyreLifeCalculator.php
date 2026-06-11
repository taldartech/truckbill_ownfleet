<?php

namespace App\Support;

use App\Tyre;
use App\TyreInstallation;

class TyreLifeCalculator
{
    public const DEFAULT_EXPECTED_LIFE_KM = 40000;

    public function __construct(private readonly TyrePositionCatalog $positionCatalog = new TyrePositionCatalog) {}

    public function expectedLifeKm(Tyre $tyre): float
    {
        $value = (float) ($tyre->expected_life_km ?? 0);

        return $value > 0 ? $value : self::DEFAULT_EXPECTED_LIFE_KM;
    }

    /**
     * @return array{
     *     expected_life_km: float,
     *     stock_km: float,
     *     active_stint_km: float,
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
        $stockKm = (float) $tyre->current_km;
        $activeStintKm = $tyre->activeInstallation
            ? $this->activeStintKm($tyre->activeInstallation)
            : 0.0;
        $totalUsed = $stockKm + $activeStintKm;

        $remaining = max(0, round($expected - $totalUsed, 2));
        $percent = $expected > 0 ? round(min(100, ($totalUsed / $expected) * 100), 1) : 0;

        $spareCount = $this->spareUsageCount($tyre);
        $repairCost = (float) $tyre->damages->sum('repair_cost');
        $expenseCost = (float) $tyre->expenses->sum('amount');
        $totalCost = (float) $tyre->purchase_cost + $expenseCost;

        return [
            'expected_life_km' => $expected,
            'stock_km' => round($stockKm, 2),
            'active_stint_km' => round($activeStintKm, 2),
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
        return max(0, (float) ($installation->stint_km ?? 0));
    }

    public function spareUsageCount(Tyre $tyre): int
    {
        $count = 0;

        foreach ($tyre->installations as $installation) {
            if ($this->positionCatalog->isSpare($this->positionValue($installation->position_code))) {
                $count++;
            }
        }

        foreach ($tyre->rotations ?? [] as $rotation) {
            if ($this->positionCatalog->isSpare($this->positionValue($rotation->new_position))) {
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
        $stockKm = $summary['stock_km'];
        $remaining = max(0, round($expected - ($stockKm + $stintKm), 2));
        $percent = $expected > 0 ? round(min(100, (($stockKm + $stintKm) / $expected) * 100), 1) : 0;

        if ($stintKm < 5000 && $remaining > ($expected * 0.5)) {
            return [
                'level' => 'warning',
                'message' => "Tyre removed after only {$stintKm} km on this truck. Expected life {$expected} km — about {$remaining} km may still be usable (check tread before scrap).",
                'remaining_life_km' => $remaining,
                'stint_km' => round($stintKm, 2),
            ];
        }

        if ($remaining <= 5000 && $percent >= 85) {
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
        return is_string($position) ? $position : null;
    }
}
