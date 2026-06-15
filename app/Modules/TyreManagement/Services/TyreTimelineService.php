<?php

namespace App\Modules\TyreManagement\Services;

use App\Enums\TyreExpenseType;
use App\Support\TyrePositionCatalog;
use App\Tyre;
use App\TyreInstallation;
use App\TyreReplacement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

            $events->push($this->event(
                type: 'expense',
                sortAt: $expense->created_at ?? $expense->expense_date,
                date: $expense->expense_date?->toDateString(),
                title: $type?->value ?? 'Expense',
                detail: '₹'.$expense->amount.($expense->notes ? " · {$expense->notes}" : ''),
                extra: ['amount' => (float) $expense->amount],
            ));
        }

        $installations = $tyre->installations
            ->sortBy(fn (TyreInstallation $installation) => $this->sortKey($installation->created_at ?? $installation->installed_at))
            ->values();

        foreach ($installations as $installation) {
            $position = $this->positionLabel($installation->position_code);
            $vehicle = $installation->vehicle?->registration_number ?? '—';
            $installedDate = $installation->installed_at?->toDateString();

            if ($installedDate !== null && in_array($installedDate, $replacementInstalledDates, true)) {
                continue;
            }

            $events->push($this->event(
                type: 'installed',
                sortAt: $installation->created_at ?? $installation->installed_at,
                date: $installedDate,
                title: "Installed on {$vehicle}",
                detail: "Position: {$position}",
                extra: [
                    'vehicle_registration' => $vehicle,
                    'position' => $this->positionValue($installation->position_code),
                    'position_label' => $position,
                ],
            ));

            if ($installation->removed_at !== null) {
                $removedDate = $installation->removed_at->toDateString();

                if (in_array($removedDate, $replacedOffDates, true)) {
                    continue;
                }

                $events->push($this->event(
                    type: 'removed',
                    sortAt: $installation->updated_at ?? $installation->removed_at,
                    date: $removedDate,
                    title: "Removed from {$vehicle}",
                    detail: "Ran {$installation->running_km} km on {$position}"
                        .($installation->removal_recommendation ? " · {$installation->removal_recommendation}" : ''),
                    extra: [
                        'vehicle_registration' => $vehicle,
                        'position_label' => $position,
                        'running_km' => (float) ($installation->running_km ?? 0),
                    ],
                ));
            }
        }

        foreach ($tyre->rotations as $rotation) {
            if (! $this->rotationBelongsToStint($rotation, $installations)) {
                continue;
            }

            $vehicle = $rotation->vehicle?->registration_number ?? '—';

            $events->push($this->event(
                type: 'rotation',
                sortAt: $rotation->created_at ?? $rotation->rotation_date,
                date: $rotation->rotation_date?->toDateString(),
                title: "Rotated on {$vehicle}",
                detail: $this->positionLabel($rotation->previous_position).' → '.$this->positionLabel($rotation->new_position)
                    .($rotation->cost !== null && (float) $rotation->cost > 0 ? ' · ₹'.number_format((float) $rotation->cost, 2) : '')
                    .($rotation->reason ? " ({$rotation->reason})" : ''),
                extra: ['vehicle_registration' => $vehicle],
            ));
        }

        foreach ($tyre->retreads as $retread) {
            $events->push($this->event(
                type: 'retread',
                sortAt: $retread->created_at ?? $retread->retread_date,
                date: $retread->retread_date?->toDateString(),
                title: 'Retread',
                detail: "{$retread->vendor} · ₹{$retread->cost}",
            ));
        }

        foreach ($tyre->damages as $damage) {
            $vehicle = $damage->vehicle?->registration_number ?? '—';
            $position = $damage->position_code ? $this->positionLabel($damage->position_code) : '—';

            $events->push($this->event(
                type: 'damage',
                sortAt: $damage->created_at ?? $damage->occurred_at,
                date: $damage->occurred_at?->toDateString(),
                title: ucfirst(strtolower(str_replace('_', ' ', $damage->damage_type?->value ?? 'damage'))),
                detail: "Truck: {$vehicle} · Position: {$position}"
                    .($damage->repair_cost ? " · Repair ₹{$damage->repair_cost}" : ''),
                extra: [
                    'vehicle_registration' => $vehicle,
                    'position_label' => $position,
                ],
            ));
        }

        foreach ($replacements as $replacement) {
            $vehicle = $replacement->vehicle?->registration_number ?? '—';
            $position = $this->positionLabel($replacement->position_code);
            $isOld = $replacement->old_tyre_id === $tyre->id;

            $events->push($this->event(
                type: 'replacement',
                sortAt: $replacement->created_at ?? $replacement->replaced_at,
                date: $replacement->replaced_at?->toDateString(),
                title: $isOld ? "Replaced off {$vehicle}" : "Installed as replacement on {$vehicle}",
                detail: "Position: {$position}"
                    .($isOld && $replacement->old_tyre_stint_km ? " · Ran {$replacement->old_tyre_stint_km} km" : '')
                    .($isOld && $replacement->old_tyre_remaining_life_km !== null ? " · ~{$replacement->old_tyre_remaining_life_km} km life left" : '')
                    .($isOld && $replacement->recommendation ? " · {$replacement->recommendation}" : ''),
                extra: [
                    'vehicle_registration' => $vehicle,
                    'position_label' => $position,
                ],
            ));
        }

        if ($tyre->scrapped_at !== null) {
            $detail = 'Marked as scrapped';
            if ($tyre->scrap_sale_amount !== null && (float) $tyre->scrap_sale_amount > 0) {
                $detail .= ' · Sale ₹'.number_format((float) $tyre->scrap_sale_amount, 2);
            }
            if ($tyre->scrap_notes) {
                $detail .= " · {$tyre->scrap_notes}";
            }

            $events->push($this->event(
                type: 'scrapped',
                sortAt: $tyre->updated_at ?? $tyre->scrapped_at,
                date: $tyre->scrapped_at->toDateString(),
                title: 'Scrapped / sold',
                detail: $detail,
            ));
        }

        return $events
            ->filter(fn (array $event) => ! empty($event['sort_at']))
            ->unique(fn (array $event) => $event['type'].'|'.$event['sort_at'].'|'.($event['title'] ?? ''))
            ->sortByDesc(fn (array $event) => $event['sort_at'])
            ->values()
            ->map(fn (array $event) => collect($event)->except('sort_priority')->all())
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TyreInstallation>  $installations
     */
    private function rotationBelongsToStint($rotation, $installations): bool
    {
        $rotationDate = $rotation->rotation_date?->toDateString();

        if ($rotationDate === null) {
            return false;
        }

        return $installations->contains(function (TyreInstallation $installation) use ($rotation, $rotationDate): bool {
            if ($rotation->vehicle_id !== $installation->vehicle_id) {
                return false;
            }

            $installedDate = $installation->installed_at?->toDateString();

            if ($installedDate === null || $rotationDate < $installedDate) {
                return false;
            }

            if ($installation->removed_at !== null && $rotationDate > $installation->removed_at->toDateString()) {
                return false;
            }

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function event(
        string $type,
        CarbonInterface|string|null $sortAt,
        ?string $date,
        string $title,
        string $detail,
        array $extra = [],
    ): array {
        $sort = $this->normalizeSortAt($sortAt);
        $date ??= $sort->toDateString();

        return array_merge([
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'date' => $date,
            'time' => $this->formatTime($sort),
            'sort_at' => $sort->toIso8601String(),
            'title' => $title,
            'detail' => $detail,
        ], $extra);
    }

    private function normalizeSortAt(CarbonInterface|string|null $value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::parse($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return Carbon::now();
    }

    private function formatTime(CarbonInterface $value): ?string
    {
        if ($value->format('H:i:s') === '00:00:00') {
            return null;
        }

        return $value->format('H:i');
    }

    private function sortKey(CarbonInterface|string|null $value): string
    {
        return $this->normalizeSortAt($value)->toIso8601String();
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'installed' => 'Install',
            'removed' => 'Remove',
            'rotation' => 'Rotation',
            'retread' => 'Retread',
            'damage' => 'Damage',
            'replacement' => 'Replace',
            'scrapped' => 'Scrap',
            'expense' => 'Expense',
            default => ucfirst($type),
        };
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
