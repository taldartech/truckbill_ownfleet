<?php

namespace App\Modules\TyreManagement\Services;

use App\Enums\TyrePosition;
use App\Support\TyreLifeCalculator;
use App\Tyre;
use App\TyreReplacement;

class TyreTimelineService
{
    public function __construct(private readonly TyreLifeCalculator $lifeCalculator) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function build(Tyre $tyre): array
    {
        $tyre->loadMissing([
            'installations.vehicle',
            'rotations.vehicle',
            'retreads',
            'damages.vehicle',
            'expenses',
        ]);

        $events = collect();

        foreach ($tyre->installations as $installation) {
            $position = $this->positionLabel($installation->position_code);
            $vehicle = $installation->vehicle?->registration_number ?? '—';

            $events->push([
                'type' => 'installed',
                'date' => $installation->installed_at?->toDateString(),
                'title' => "Installed on {$vehicle}",
                'detail' => "Position: {$position} · Odometer: {$installation->km_at_installation} km",
                'vehicle_registration' => $vehicle,
                'position' => $this->positionValue($installation->position_code),
                'position_label' => $position,
                'km' => (float) $installation->km_at_installation,
                'is_spare' => $this->positionValue($installation->position_code) === TyrePosition::Spare->value,
            ]);

            if ($installation->removed_at !== null) {
                $events->push([
                    'type' => 'removed',
                    'date' => $installation->removed_at->toDateString(),
                    'title' => "Removed from {$vehicle}",
                    'detail' => "Ran {$installation->running_km} km on {$position}"
                        .($installation->removal_recommendation ? " · {$installation->removal_recommendation}" : ''),
                    'vehicle_registration' => $vehicle,
                    'position' => $this->positionValue($installation->position_code),
                    'position_label' => $position,
                    'km' => (float) ($installation->km_at_removal ?? 0),
                    'running_km' => (float) ($installation->running_km ?? 0),
                    'recommendation' => $installation->removal_recommendation,
                ]);
            }
        }

        foreach ($tyre->rotations as $rotation) {
            $vehicle = $rotation->vehicle?->registration_number ?? '—';
            $events->push([
                'type' => 'rotation',
                'date' => $rotation->rotation_date?->toDateString(),
                'title' => "Rotated on {$vehicle}",
                'detail' => $this->positionLabel($rotation->previous_position).' → '.$this->positionLabel($rotation->new_position)
                    .($rotation->reason ? " ({$rotation->reason})" : ''),
                'vehicle_registration' => $vehicle,
                'from_position' => $this->positionValue($rotation->previous_position),
                'to_position' => $this->positionValue($rotation->new_position),
                'km' => $rotation->km_at_rotation !== null ? (float) $rotation->km_at_rotation : null,
                'is_spare' => $this->positionValue($rotation->new_position) === TyrePosition::Spare->value,
            ]);
        }

        foreach ($tyre->damages as $damage) {
            $vehicle = $damage->vehicle?->registration_number ?? '—';
            $position = $damage->position_code ? $this->positionLabel($damage->position_code) : '—';
            $events->push([
                'type' => 'damage',
                'date' => $damage->occurred_at?->toDateString(),
                'title' => ucfirst(strtolower(str_replace('_', ' ', $damage->damage_type?->value ?? 'damage'))),
                'detail' => "Truck: {$vehicle} · Position: {$position}"
                    .($damage->km_at_occurrence !== null ? " · KM: {$damage->km_at_occurrence}" : '')
                    .($damage->repair_cost ? " · Repair ₹{$damage->repair_cost}" : ''),
                'vehicle_registration' => $vehicle,
                'position_label' => $position,
                'damage_type' => $damage->damage_type?->value,
                'repair_cost' => $damage->repair_cost !== null ? (float) $damage->repair_cost : null,
            ]);
        }

        foreach ($tyre->retreads as $retread) {
            $events->push([
                'type' => 'retread',
                'date' => $retread->retread_date?->toDateString(),
                'title' => 'Retread',
                'detail' => "{$retread->vendor} · ₹{$retread->cost}",
            ]);
        }

        foreach ($tyre->expenses as $expense) {
            $events->push([
                'type' => 'expense',
                'date' => $expense->expense_date?->toDateString(),
                'title' => $expense->expense_type?->value ?? 'Expense',
                'detail' => "₹{$expense->amount}".($expense->notes ? " · {$expense->notes}" : ''),
                'amount' => (float) $expense->amount,
            ]);
        }

        TyreReplacement::query()
            ->where('old_tyre_id', $tyre->id)
            ->orWhere('new_tyre_id', $tyre->id)
            ->with(['vehicle', 'oldTyre', 'newTyre'])
            ->get()
            ->each(function (TyreReplacement $replacement) use ($events, $tyre): void {
                $vehicle = $replacement->vehicle?->registration_number ?? '—';
                $position = $this->positionLabel($replacement->position_code);
                $isOld = $replacement->old_tyre_id === $tyre->id;

                $events->push([
                    'type' => 'replacement',
                    'date' => $replacement->replaced_at?->toDateString(),
                    'title' => $isOld ? "Replaced off {$vehicle}" : "Installed as replacement on {$vehicle}",
                    'detail' => "Position: {$position}"
                        .($isOld && $replacement->old_tyre_stint_km ? " · Ran {$replacement->old_tyre_stint_km} km" : '')
                        .($isOld && $replacement->old_tyre_remaining_life_km !== null ? " · ~{$replacement->old_tyre_remaining_life_km} km life left" : '')
                        .($replacement->recommendation ? " · {$replacement->recommendation}" : ''),
                    'vehicle_registration' => $vehicle,
                    'position_label' => $position,
                    'recommendation' => $replacement->recommendation,
                ]);
            });

        return $events
            ->filter(fn (array $event) => ! empty($event['date']))
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    private function positionLabel(mixed $position): string
    {
        if ($position instanceof TyrePosition) {
            return $position->label();
        }

        $enum = is_string($position) ? TyrePosition::tryFrom($position) : null;

        return $enum?->label() ?? (string) $position;
    }

    private function positionValue(mixed $position): ?string
    {
        if ($position instanceof TyrePosition) {
            return $position->value;
        }

        return is_string($position) ? $position : null;
    }
}
