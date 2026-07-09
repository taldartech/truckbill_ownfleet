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
            'damages.vehicle',
            'expenses',
        ]);

        $events = collect();

        $replacements = TyreReplacement::query()
            ->where('old_tyre_id', $tyre->id)
            ->orWhere('new_tyre_id', $tyre->id)
            ->with(['vehicle', 'oldTyre', 'newTyre'])
            ->get();

        $replacementInstallKeys = $replacements
            ->where('new_tyre_id', $tyre->id)
            ->map(fn (TyreReplacement $replacement) => $this->vehicleDateKey(
                $replacement->replaced_at?->toDateString(),
                $replacement->vehicle_id,
            ))
            ->filter()
            ->all();

        $replacedOffKeys = $replacements
            ->where('old_tyre_id', $tyre->id)
            ->map(fn (TyreReplacement $replacement) => $this->vehicleDateKey(
                $replacement->replaced_at?->toDateString(),
                $replacement->vehicle_id,
            ))
            ->filter()
            ->all();

        foreach ($tyre->expenses as $expense) {
            $type = $expense->expense_type;

            if (in_array($type, [TyreExpenseType::Rotation, TyreExpenseType::Retread, TyreExpenseType::Repair], true)) {
                continue;
            }

            $events->push($this->event(
                type: 'expense',
                sortAt: $this->resolveSortAt($expense->expense_date, $expense->created_at),
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
            $installKey = $this->vehicleDateKey($installedDate, $installation->vehicle_id);

            if ($installKey !== null && in_array($installKey, $replacementInstallKeys, true)) {
                continue;
            }

            $events->push($this->event(
                type: 'installed',
                sortAt: $this->resolveSortAt($installation->installed_at, $installation->created_at),
                date: $installedDate,
                title: "Installed on {$vehicle}",
                detail: $this->installationEventDetail($installation, $position),
                extra: [
                    'installation_id' => $installation->id,
                    'vehicle_registration' => $vehicle,
                    'position' => $this->positionValue($installation->position_code),
                    'position_label' => $position,
                ],
            ));

            if ($installation->removed_at !== null) {
                $removedDate = $installation->removed_at->toDateString();
                $removeKey = $this->vehicleDateKey($removedDate, $installation->vehicle_id);

                if ($removeKey !== null && in_array($removeKey, $replacedOffKeys, true)) {
                    continue;
                }

                $events->push($this->event(
                    type: 'removed',
                    sortAt: $this->resolveSortAt($installation->removed_at, $installation->updated_at),
                    date: $removedDate,
                    title: "Removed from {$vehicle}",
                    detail: $this->removalEventDetail($installation, $position),
                    extra: [
                        'installation_id' => $installation->id,
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
                sortAt: $this->resolveSortAt($rotation->rotation_date, $rotation->created_at),
                date: $rotation->rotation_date?->toDateString(),
                title: "Rotated on {$vehicle}",
                detail: $this->positionLabel($rotation->previous_position).' → '.$this->positionLabel($rotation->new_position)
                    .($rotation->cost !== null && (float) $rotation->cost > 0 ? ' · ₹'.number_format((float) $rotation->cost, 2) : '')
                    .($rotation->reason ? " ({$rotation->reason})" : ''),
                extra: ['vehicle_registration' => $vehicle],
            ));
        }

        foreach ($tyre->damages as $damage) {
            $vehicle = $damage->vehicle?->registration_number ?? '—';
            $position = $damage->position_code ? $this->positionLabel($damage->position_code) : '—';

            $events->push($this->event(
                type: 'damage',
                sortAt: $this->resolveSortAt($damage->occurred_at, $damage->created_at),
                date: $damage->occurred_at?->toDateString(),
                title: $this->damageTitle($damage->damage_type?->value),
                detail: "Truck: {$vehicle} · Position: {$position}"
                    .($damage->repair_cost ? ' · Repair ₹'.number_format((float) $damage->repair_cost, 2) : ''),
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
                sortAt: $this->resolveSortAt($replacement->replaced_at, $replacement->created_at),
                date: $replacement->replaced_at?->toDateString(),
                title: $isOld ? "Replaced off {$vehicle}" : "Installed as replacement on {$vehicle}",
                detail: $this->replacementEventDetail($replacement, $isOld, $position),
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
                sortAt: $this->resolveSortAt($tyre->scrapped_at, $tyre->updated_at),
                date: $tyre->scrapped_at->toDateString(),
                title: 'Scrapped / sold',
                detail: $detail,
            ));
        }

        return $events
            ->filter(fn (array $event) => ! empty($event['sort_at']))
            ->unique(fn (array $event) => $this->eventIdentity($event))
            ->sortByDesc(fn (array $event) => [$event['sort_at'], $event['sort_priority'] ?? 0])
            ->values()
            ->map(fn (array $event) => collect($event)->except('sort_priority')->all())
            ->all();
    }

    private function eventIdentity(array $event): string
    {
        if (! empty($event['installation_id'])) {
            return $event['type'].'|'.$event['installation_id'];
        }

        return $event['type'].'|'.$event['sort_at'].'|'.($event['title'] ?? '');
    }

    private function vehicleDateKey(?string $date, mixed $vehicleId): ?string
    {
        if ($date === null || $vehicleId === null || $vehicleId === '') {
            return null;
        }

        return $date.'|'.$vehicleId;
    }

    private function installationEventDetail(TyreInstallation $installation, string $position): string
    {
        $detail = "Position: {$position}";
        $installedDate = $installation->installed_at?->toDateString();

        if ($installation->removed_at !== null) {
            $removedDate = $installation->removed_at->toDateString();
            $detail .= " · {$installedDate} → {$removedDate}";

            if ($installation->running_km !== null) {
                $detail .= ' · Ran '.number_format((float) $installation->running_km, 2).' km';
            }
        } elseif ($installedDate !== null) {
            $detail .= " · Since {$installedDate}";
        }

        return $detail;
    }

    private function removalEventDetail(TyreInstallation $installation, string $position): string
    {
        $detail = 'Ran '.number_format((float) ($installation->running_km ?? 0), 2)." km on {$position}";

        if ($installation->removal_recommendation) {
            $detail .= " · {$installation->removal_recommendation}";
        }

        return $detail;
    }

    private function replacementEventDetail(TyreReplacement $replacement, bool $isOld, string $position): string
    {
        $parts = ["Position: {$position}"];

        if ($isOld) {
            $parts[] = 'Ran '.number_format((float) ($replacement->old_tyre_stint_km ?? 0), 2).' km';

            if ($replacement->recommendation) {
                $parts[] = $replacement->recommendation;
            } else {
                $remaining = max(0, (float) ($replacement->old_tyre_remaining_life_km ?? 0));

                if ($remaining > 0) {
                    $parts[] = '~'.number_format($remaining, 0).' km life left';
                }
            }
        }

        return implode(' · ', $parts);
    }

    private function damageTitle(?string $damageType): string
    {
        return match (strtoupper((string) $damageType)) {
            'RECAPPED' => 'Regrip',
            default => ucfirst(strtolower(str_replace('_', ' ', $damageType ?? 'damage'))),
        };
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
            'time' => null,
            'sort_at' => $sort->toIso8601String(),
            'sort_priority' => $this->typeSortPriority($type),
            'title' => $title,
            'detail' => $detail,
        ], $extra);
    }

    private function typeSortPriority(string $type): int
    {
        return match ($type) {
            'expense' => 5,
            'installed' => 10,
            'removed' => 20,
            'rotation' => 30,
            'damage' => 40,
            'replacement' => 50,
            'scrapped' => 60,
            default => 0,
        };
    }

    private function resolveSortAt(?CarbonInterface $businessDate, ?CarbonInterface $recordTimestamp): Carbon
    {
        if ($businessDate === null) {
            return $this->normalizeSortAt($recordTimestamp);
        }

        if ($recordTimestamp !== null && $businessDate->toDateString() === $recordTimestamp->toDateString()) {
            return Carbon::parse($recordTimestamp);
        }

        return Carbon::parse($businessDate)->startOfDay();
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
            'damage' => 'Repair',
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
