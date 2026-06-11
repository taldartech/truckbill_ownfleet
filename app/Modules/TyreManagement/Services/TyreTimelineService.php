<?php

namespace App\Modules\TyreManagement\Services;

use App\Enums\TyreExpenseType;
use App\Support\TyrePositionCatalog;
use App\Tyre;
use App\TyreInstallation;
use App\TyreReplacement;

class TyreTimelineService
{
    public function __construct(private readonly TyrePositionCatalog $positionCatalog = new TyrePositionCatalog) {}

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

        $replacements = TyreReplacement::query()
            ->where('old_tyre_id', $tyre->id)
            ->orWhere('new_tyre_id', $tyre->id)
            ->with(['vehicle', 'oldTyre', 'newTyre'])
            ->get();

        $replacementInstalledDates = $replacements
            ->where('new_tyre_id', $tyre->id)
            ->pluck('replaced_at')
            ->map(fn ($date) => $date?->toDateString())
            ->filter()
            ->all();

        $replacedOffDates = $replacements
            ->where('old_tyre_id', $tyre->id)
            ->pluck('replaced_at')
            ->map(fn ($date) => $date?->toDateString())
            ->filter()
            ->all();

        foreach ($tyre->expenses as $expense) {
            $type = $expense->expense_type;

            if (in_array($type, [TyreExpenseType::Rotation, TyreExpenseType::Retread, TyreExpenseType::Repair], true)) {
                continue;
            }

            $events->push([
                'type' => 'expense',
                'date' => $expense->expense_date?->toDateString(),
                'title' => $type?->value ?? 'Expense',
                'detail' => '₹'.$expense->amount.($expense->notes ? " · {$expense->notes}" : ''),
                'amount' => (float) $expense->amount,
                'sort_priority' => $type === TyreExpenseType::Purchase ? 10 : 50,
            ]);
        }

        $installations = $tyre->installations
            ->sortBy(fn (TyreInstallation $installation) => $installation->installed_at?->toDateString().$installation->created_at?->toIso8601String())
            ->values();

        foreach ($installations as $installation) {
            $position = $this->positionLabel($installation->position_code);
            $vehicle = $installation->vehicle?->registration_number ?? '—';
            $installedDate = $installation->installed_at?->toDateString();

            if ($installedDate !== null && in_array($installedDate, $replacementInstalledDates, true)) {
                continue;
            }

            $events->push([
                'type' => 'installed',
                'date' => $installedDate,
                'title' => "Installed on {$vehicle}",
                'detail' => "Position: {$position}",
                'vehicle_registration' => $vehicle,
                'position' => $this->positionValue($installation->position_code),
                'position_label' => $position,
                'sort_priority' => 20,
            ]);

            foreach ($tyre->rotations as $rotation) {
                if ($rotation->vehicle_id !== $installation->vehicle_id) {
                    continue;
                }

                $rotationDate = $rotation->rotation_date?->toDateString();

                if ($rotationDate === null || $rotationDate < $installedDate) {
                    continue;
                }

                if ($installation->removed_at !== null && $rotationDate > $installation->removed_at->toDateString()) {
                    continue;
                }

                $events->push([
                    'type' => 'rotation',
                    'date' => $rotationDate,
                    'title' => "Rotated on {$vehicle}",
                    'detail' => $this->positionLabel($rotation->previous_position).' → '.$this->positionLabel($rotation->new_position)
                        .($rotation->cost !== null && (float) $rotation->cost > 0 ? ' · ₹'.number_format((float) $rotation->cost, 2) : '')
                        .($rotation->reason ? " ({$rotation->reason})" : ''),
                    'vehicle_registration' => $vehicle,
                    'sort_priority' => 30,
                ]);
            }

            if ($installation->removed_at !== null) {
                $removedDate = $installation->removed_at->toDateString();

                if (in_array($removedDate, $replacedOffDates, true)) {
                    continue;
                }

                $events->push([
                    'type' => 'removed',
                    'date' => $removedDate,
                    'title' => "Removed from {$vehicle}",
                    'detail' => "Ran {$installation->running_km} km on {$position}"
                        .($installation->removal_recommendation ? " · {$installation->removal_recommendation}" : ''),
                    'vehicle_registration' => $vehicle,
                    'position_label' => $position,
                    'running_km' => (float) ($installation->running_km ?? 0),
                    'sort_priority' => 60,
                ]);
            }
        }

        foreach ($tyre->retreads as $retread) {
            $events->push([
                'type' => 'retread',
                'date' => $retread->retread_date?->toDateString(),
                'title' => 'Retread',
                'detail' => "{$retread->vendor} · ₹{$retread->cost}",
                'sort_priority' => 40,
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
                    .($damage->repair_cost ? " · Repair ₹{$damage->repair_cost}" : ''),
                'vehicle_registration' => $vehicle,
                'position_label' => $position,
                'sort_priority' => 40,
            ]);
        }

        foreach ($replacements as $replacement) {
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
                    .($isOld && $replacement->recommendation ? " · {$replacement->recommendation}" : ''),
                'vehicle_registration' => $vehicle,
                'position_label' => $position,
                'sort_priority' => $isOld ? 70 : 20,
            ]);
        }

        if ($tyre->scrapped_at !== null) {
            $detail = 'Marked as scrapped';
            if ($tyre->scrap_sale_amount !== null && (float) $tyre->scrap_sale_amount > 0) {
                $detail .= ' · Sale ₹'.number_format((float) $tyre->scrap_sale_amount, 2);
            }
            if ($tyre->scrap_notes) {
                $detail .= " · {$tyre->scrap_notes}";
            }

            $events->push([
                'type' => 'scrapped',
                'date' => $tyre->scrapped_at->toDateString(),
                'title' => 'Scrapped / sold',
                'detail' => $detail,
                'sort_priority' => 80,
            ]);
        }

        return $events
            ->filter(fn (array $event) => ! empty($event['date']))
            ->unique(fn (array $event) => $event['type'].'|'.$event['date'].'|'.($event['title'] ?? ''))
            ->sortBy(fn (array $event) => $event['date'].'-'.str_pad((string) ($event['sort_priority'] ?? 50), 2, '0', STR_PAD_LEFT))
            ->values()
            ->map(fn (array $event) => collect($event)->except('sort_priority')->all())
            ->all();
    }

    private function positionLabel(mixed $position): string
    {
        return $this->positionCatalog->labelFor(is_string($position) ? $position : null);
    }

    private function positionValue(mixed $position): ?string
    {
        return is_string($position) ? $position : null;
    }
}
