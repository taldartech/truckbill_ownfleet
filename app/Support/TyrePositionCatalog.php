<?php

namespace App\Support;

use App\Enums\TyrePosition;
use App\TyreLayout;
use App\Vehicle;

class TyrePositionCatalog
{
    /**
     * @return list<array{code: string, label: string, is_spare: bool, axle: int|null}>
     */
    public function positionsForVehicle(Vehicle $vehicle): array
    {
        $layout = $this->resolveLayoutForVehicle($vehicle);

        if ($layout === null) {
            return $this->legacyDualRearPositions();
        }

        return $this->positionsForTyreLayout($layout);
    }

    /**
     * @return list<array{code: string, label: string, is_spare: bool, axle: int|null}>
     */
    public function positionsForTyreLayout(TyreLayout $layout): array
    {
        if ($layout->isLegacyDualRear()) {
            return $this->legacyDualRearPositions();
        }

        return $this->buildLayout(
            steerAxles: (int) $layout->steer_axles,
            dualAxles: array_map('intval', $layout->dual_axles ?? []),
            singleAxles: array_map('intval', $layout->single_axles ?? []),
            spareCount: (int) $layout->spare_count,
        );
    }

    public function isAllowedOnVehicle(Vehicle $vehicle, string $positionCode): bool
    {
        return collect($this->positionsForVehicle($vehicle))
            ->contains(fn (array $slot): bool => $slot['code'] === $positionCode);
    }

    public function labelFor(?string $positionCode): string
    {
        if ($positionCode === null || $positionCode === '') {
            return '—';
        }

        $legacy = TyrePosition::tryFrom($positionCode);
        if ($legacy !== null) {
            return $legacy->label();
        }

        $layouts = TyreLayout::query()->get();
        foreach ($layouts as $layout) {
            foreach ($this->positionsForTyreLayout($layout) as $slot) {
                if ($slot['code'] === $positionCode) {
                    return $slot['label'];
                }
            }
        }

        return str_replace('_', ' ', strtolower($positionCode));
    }

    public function isSpare(?string $positionCode): bool
    {
        if ($positionCode === null || $positionCode === '') {
            return false;
        }

        if (str_starts_with($positionCode, 'SPARE')) {
            return true;
        }

        return TyrePosition::tryFrom($positionCode) === TyrePosition::Spare;
    }

    public function isSteerPosition(?string $positionCode): bool
    {
        if ($positionCode === null || $positionCode === '') {
            return false;
        }

        return str_starts_with($positionCode, 'STEER')
            || in_array($positionCode, [TyrePosition::FrontLeft->value, TyrePosition::FrontRight->value], true);
    }

    private function resolveLayoutForVehicle(Vehicle $vehicle): ?TyreLayout
    {
        if ($vehicle->tyre_layout === null || $vehicle->tyre_layout === '') {
            return TyreLayout::query()
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();
        }

        return TyreLayout::query()
            ->where('tenant_id', $vehicle->tenant_id)
            ->where('code', $vehicle->tyre_layout)
            ->first();
    }

    /**
     * @return list<array{code: string, label: string, is_spare: bool, axle: int|null}>
     */
    private function legacyDualRearPositions(): array
    {
        return collect(TyrePosition::cases())
            ->map(fn (TyrePosition $position): array => [
                'code' => $position->value,
                'label' => $position->label(),
                'is_spare' => $position === TyrePosition::Spare,
                'axle' => match ($position) {
                    TyrePosition::FrontLeft, TyrePosition::FrontRight => 0,
                    TyrePosition::Spare => null,
                    default => 1,
                },
            ])
            ->all();
    }

    /**
     * @param  list<int>  $dualAxles
     * @param  list<int>  $singleAxles
     * @return list<array{code: string, label: string, is_spare: bool, axle: int|null}>
     */
    private function buildLayout(int $steerAxles, array $dualAxles, array $singleAxles, int $spareCount): array
    {
        $positions = [];

        for ($axle = 1; $axle <= $steerAxles; $axle++) {
            $positions[] = $this->steerSlot($axle, 'L');
            $positions[] = $this->steerSlot($axle, 'R');
        }

        foreach ($dualAxles as $axle) {
            $positions[] = $this->dualSlot($axle, 'L', 'OUT');
            $positions[] = $this->dualSlot($axle, 'L', 'IN');
            $positions[] = $this->dualSlot($axle, 'R', 'OUT');
            $positions[] = $this->dualSlot($axle, 'R', 'IN');
        }

        foreach ($singleAxles as $axle) {
            $positions[] = $this->singleSlot($axle, 'L');
            $positions[] = $this->singleSlot($axle, 'R');
        }

        for ($spare = 1; $spare <= $spareCount; $spare++) {
            $positions[] = [
                'code' => "SPARE_{$spare}",
                'label' => $spareCount === 1 ? 'Spare' : "Spare {$spare}",
                'is_spare' => true,
                'axle' => null,
            ];
        }

        return $positions;
    }

    /**
     * @return array{code: string, label: string, is_spare: bool, axle: int|null}
     */
    private function steerSlot(int $axle, string $side): array
    {
        $code = $axle === 1 ? "STEER_{$side}" : "STEER{$axle}_{$side}";
        $label = $axle === 1
            ? ($side === 'L' ? 'Steer — Left' : 'Steer — Right')
            : ("Steer axle {$axle} — ".($side === 'L' ? 'Left' : 'Right'));

        return [
            'code' => $code,
            'label' => $label,
            'is_spare' => false,
            'axle' => 0,
        ];
    }

    /**
     * @return array{code: string, label: string, is_spare: bool, axle: int|null}
     */
    private function dualSlot(int $axle, string $side, string $role): array
    {
        $sideLabel = $side === 'L' ? 'Left' : 'Right';
        $roleLabel = $role === 'OUT' ? 'Outer' : 'Inner';

        return [
            'code' => "AX{$axle}_{$side}_{$role}",
            'label' => "Axle {$axle} — {$sideLabel} ({$roleLabel})",
            'is_spare' => false,
            'axle' => $axle,
        ];
    }

    /**
     * @return array{code: string, label: string, is_spare: bool, axle: int|null}
     */
    private function singleSlot(int $axle, string $side): array
    {
        return [
            'code' => "AX{$axle}_{$side}",
            'label' => 'Axle '.$axle.' — '.($side === 'L' ? 'Left' : 'Right'),
            'is_spare' => false,
            'axle' => $axle,
        ];
    }
}
