<?php

namespace App\Modules\TyreManagement\Services;

use App\Enums\TyreExpenseType;
use App\Enums\TyreStatus;
use App\Modules\TyreManagement\Repositories\TyreRepository;
use App\Support\TyreLifeCalculator;
use App\Support\TyrePositionCatalog;
use App\Support\TyreStintCalculator;
use App\Tyre;
use App\TyreDamage;
use App\TyreExpense;
use App\TyreInstallation;
use App\TyreReplacement;
use App\TyreRotation;
use App\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TyreService
{
    public function __construct(
        private readonly TyreRepository $tyreRepository,
        private readonly TyreLifeCalculator $lifeCalculator,
        private readonly TyreTimelineService $timelineService,
        private readonly TyrePositionCatalog $positionCatalog,
        private readonly TyreStintCalculator $stintCalculator,
    ) {}

    public function paginate(int $perPage = 15, ?string $search = null, ?string $status = null, ?string $vehicleId = null): LengthAwarePaginator
    {
        return $this->tyreRepository->paginate($perPage, $search, $status, $vehicleId);
    }

    public function show(string $id): Tyre
    {
        return $this->tyreRepository->findWithHistory($id);
    }

    /**
     * @return array{tyre: Tyre, life_summary: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function showWithLifecycle(string $id): array
    {
        $tyre = $this->show($id);

        return [
            'tyre' => $tyre,
            'life_summary' => $this->lifeCalculator->summary($tyre),
            'timeline' => $this->timelineService->build($tyre),
        ];
    }

    public function create(array $data): Tyre
    {
        return DB::transaction(function () use ($data): Tyre {
            $data['status'] = $data['status'] ?? TyreStatus::InStock->value;
            $data['expected_life_km'] = $data['expected_life_km'] ?? TyreLifeCalculator::DEFAULT_EXPECTED_LIFE_KM;

            $tyre = $this->tyreRepository->create($data);

            if ((float) ($data['purchase_cost'] ?? 0) > 0) {
                $this->recordExpense($tyre, TyreExpenseType::Purchase, (float) $data['purchase_cost'], $data['purchase_date'] ?? now()->toDateString(), 'Initial purchase');
            }

            return $tyre->fresh(['activeInstallation.vehicle']);
        });
    }

    public function update(Tyre $tyre, array $data): Tyre
    {
        if (array_key_exists('current_km', $data)) {
            $this->assertCurrentKmCanBeSet($tyre, (float) $data['current_km']);
        }

        return $this->tyreRepository->update($tyre, $data)->fresh(['activeInstallation.vehicle']);
    }

    public function install(Tyre $tyre, array $data): TyreInstallation
    {
        return DB::transaction(function () use ($tyre, $data): TyreInstallation {
            if ($tyre->status === TyreStatus::Scrapped) {
                throw ValidationException::withMessages(['tyre' => 'Scrapped tyres cannot be installed.']);
            }

            if ($tyre->activeInstallation()->exists()) {
                throw ValidationException::withMessages(['tyre' => 'Tyre is already installed on a truck.']);
            }

            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
            $positionCode = (string) $data['position_code'];

            $this->assertPositionAvailable($vehicle, $positionCode, null);

            $kmAtInstallation = $this->stintCalculator->resolveInstallOdometer(
                $vehicle,
                $data['km_at_installation'] ?? null,
            );

            $installation = TyreInstallation::query()->create([
                'tyre_id' => $tyre->id,
                'vehicle_id' => $vehicle->id,
                'position_code' => $positionCode,
                'installed_at' => $data['installed_at'],
                'km_at_installation' => $kmAtInstallation,
                'stint_km' => 0,
            ]);

            $tyre->update(['status' => TyreStatus::Installed]);

            return $installation->load('vehicle');
        });
    }

    public function removeInstallation(TyreInstallation $installation, array $data): TyreInstallation
    {
        return DB::transaction(function () use ($installation, $data): TyreInstallation {
            if ($installation->removed_at !== null) {
                throw ValidationException::withMessages(['installation' => 'Tyre is already removed from this truck.']);
            }

            $runningKm = $this->stintCalculator->resolveStintForInstallation($installation, $data);
            $odometerReading = $this->stintCalculator->resolveOdometerReading($data);
            $kmAtRemoval = $odometerReading ?? $this->stintCalculator->stintFromOdometer(
                (float) ($installation->km_at_installation ?? 0),
                (float) ($installation->km_at_installation ?? 0) + $runningKm,
            );
            $tyre = $installation->tyre;
            $recommendation = $this->lifeCalculator->removalRecommendation($tyre, $runningKm);

            $installation->update([
                'removed_at' => $data['removed_at'] ?? now()->toDateString(),
                'stint_km' => $runningKm,
                'running_km' => $runningKm,
                'km_at_removal' => $kmAtRemoval,
                'removal_recommendation' => $recommendation['message'] ?? null,
            ]);

            $tyre->update([
                'status' => TyreStatus::InStock,
                'current_km' => (float) $tyre->current_km + $runningKm,
            ]);

            $installation->loadMissing('vehicle');
            if ($installation->vehicle !== null && $odometerReading !== null) {
                $this->stintCalculator->syncVehicleOdometer($installation->vehicle, $odometerReading);
            }

            $installation = $installation->fresh(['vehicle', 'tyre']);
            $installation->setAttribute('removal_advice', $recommendation);

            return $installation;
        });
    }

    public function rotate(Tyre $tyre, array $data): TyreRotation
    {
        return DB::transaction(function () use ($tyre, $data): TyreRotation {
            $installation = $tyre->activeInstallation;

            if ($installation === null) {
                throw ValidationException::withMessages(['tyre' => 'Tyre must be installed before rotation.']);
            }

            $previousCode = (string) $data['previous_position'];
            $newPositionCode = (string) $data['new_position'];
            $currentCode = (string) $installation->position_code;

            if ($currentCode !== $previousCode) {
                throw ValidationException::withMessages(['previous_position' => 'Does not match current tyre position.']);
            }

            $vehicle = $installation->vehicle ?? Vehicle::query()->findOrFail($installation->vehicle_id);

            if (! $this->positionCatalog->isAllowedOnVehicle($vehicle, $newPositionCode)) {
                throw ValidationException::withMessages([
                    'new_position' => 'This position is not valid for the selected truck layout.',
                ]);
            }

            $installation = TyreInstallation::query()
                ->whereKey($installation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $conflictingInstallation = TyreInstallation::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('position_code', $newPositionCode)
                ->whereNull('removed_at')
                ->where('id', '!=', $installation->id)
                ->lockForUpdate()
                ->first();

            $rotationCost = (float) ($data['cost'] ?? 0);
            $stintKm = $this->stintCalculator->resolveStintForInstallation($installation, $data);
            $odometerReading = $this->stintCalculator->resolveOdometerReading($data);

            $rotation = TyreRotation::query()->create([
                'tyre_id' => $tyre->id,
                'vehicle_id' => $installation->vehicle_id,
                'rotation_date' => $data['rotation_date'],
                'previous_position' => $previousCode,
                'new_position' => $newPositionCode,
                'reason' => $data['reason'] ?? null,
                'cost' => $rotationCost,
                'km_at_rotation' => $stintKm,
            ]);

            if ($conflictingInstallation !== null) {
                TyreRotation::query()->create([
                    'tyre_id' => $conflictingInstallation->tyre_id,
                    'vehicle_id' => $installation->vehicle_id,
                    'rotation_date' => $data['rotation_date'],
                    'previous_position' => $newPositionCode,
                    'new_position' => $previousCode,
                    'reason' => $data['reason'] ?? null,
                    'cost' => null,
                    'km_at_rotation' => $stintKm,
                ]);

                $swapBufferPosition = '__SWAP__'.$installation->id;
                $installation->update(['position_code' => $swapBufferPosition]);
                $conflictingInstallation->update(['position_code' => $previousCode]);
            }

            $installation->update([
                'position_code' => $newPositionCode,
                'stint_km' => $stintKm,
            ]);

            if ($odometerReading !== null) {
                $this->stintCalculator->syncVehicleOdometer($vehicle, $odometerReading);
            }

            if ($rotationCost > 0) {
                $this->recordExpense(
                    $tyre,
                    TyreExpenseType::Rotation,
                    $rotationCost,
                    $data['rotation_date'],
                    $data['reason'] ?? 'Tyre rotation',
                );
            }

            return $rotation->load('vehicle');
        });
    }

    public function recordDamage(Tyre $tyre, array $data): TyreDamage
    {
        return DB::transaction(function () use ($tyre, $data): TyreDamage {
            $active = $tyre->activeInstallation;
            $odometerReading = $this->stintCalculator->resolveOdometerReading($data);

            if ($odometerReading === null) {
                throw ValidationException::withMessages([
                    'odometer_km' => 'Current meter reading is required.',
                ]);
            }

            if ($active !== null) {
                $this->stintCalculator->stintFromInstallationOdometer($active, $odometerReading);
                $active->loadMissing('vehicle');

                if ($active->vehicle !== null) {
                    $this->stintCalculator->syncVehicleOdometer($active->vehicle, $odometerReading);
                }
            }

            $damage = TyreDamage::query()->create([
                'tyre_id' => $tyre->id,
                'vehicle_id' => $data['vehicle_id'] ?? $active?->vehicle_id,
                'position_code' => $data['position_code'] ?? $active?->position_code,
                'damage_type' => $data['damage_type'],
                'occurred_at' => $data['occurred_at'],
                'km_at_occurrence' => $odometerReading,
                'description' => $data['description'] ?? null,
                'repair_cost' => $data['repair_cost'] ?? null,
                'is_repaired' => $data['is_repaired'] ?? false,
            ]);

            if (! empty($data['repair_cost']) && (float) $data['repair_cost'] > 0) {
                $this->recordExpense($tyre, TyreExpenseType::Repair, (float) $data['repair_cost'], $data['occurred_at'], $data['description'] ?? 'Tyre repair');
            }

            return $damage->load('vehicle');
        });
    }

    public function replaceOnVehicle(array $data): TyreReplacement
    {
        return DB::transaction(function () use ($data): TyreReplacement {
            $oldTyre = Tyre::query()->findOrFail($data['old_tyre_id']);
            $newTyre = isset($data['new_tyre_id']) ? Tyre::query()->findOrFail($data['new_tyre_id']) : null;
            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
            $positionCode = (string) $data['position_code'];

            $active = $oldTyre->activeInstallation;

            if ($active === null || $active->vehicle_id !== $vehicle->id) {
                throw ValidationException::withMessages(['old_tyre_id' => 'Old tyre is not installed on the selected truck.']);
            }

            $stintKm = $this->stintCalculator->resolveStintForInstallation($active, $data);
            $odometerReading = $this->stintCalculator->resolveOdometerReading($data);
            $advice = $this->lifeCalculator->removalRecommendation($oldTyre, $stintKm);

            $this->removeInstallation($active, [
                'removed_at' => $data['replaced_at'],
                'stint_km' => $stintKm,
                'odometer_km' => $odometerReading,
            ]);

            $oldTyre->refresh();
            $remainingAfter = $this->lifeCalculator->summary($oldTyre)['remaining_life_km'];

            if ($newTyre !== null) {
                $this->install($newTyre, [
                    'vehicle_id' => $vehicle->id,
                    'position_code' => $positionCode,
                    'installed_at' => $data['replaced_at'],
                    'km_at_installation' => $odometerReading ?? $this->stintCalculator->resolveInstallOdometer($vehicle, null),
                ]);
            }

            return TyreReplacement::query()->create([
                'vehicle_id' => $vehicle->id,
                'position_code' => $positionCode,
                'old_tyre_id' => $oldTyre->id,
                'new_tyre_id' => $newTyre?->id,
                'replaced_at' => $data['replaced_at'],
                'reason' => $data['reason'] ?? null,
                'km_at_replacement' => $stintKm,
                'old_tyre_stint_km' => $stintKm,
                'old_tyre_remaining_life_km' => $remainingAfter,
                'recommendation' => $advice['message'] ?? null,
            ])->load(['vehicle', 'oldTyre', 'newTyre']);
        });
    }

    public function scrap(Tyre $tyre, array $data): Tyre
    {
        return DB::transaction(function () use ($tyre, $data): Tyre {
            if ($tyre->activeInstallation()->exists()) {
                throw ValidationException::withMessages(['tyre' => 'Remove tyre from truck before scrapping.']);
            }

            $tyre->update([
                'status' => TyreStatus::Scrapped,
                'scrapped_at' => $data['scrapped_at'] ?? now()->toDateString(),
                'scrap_notes' => $data['scrap_notes'] ?? null,
                'scrap_sale_amount' => $data['scrap_sale_amount'] ?? null,
            ]);

            return $tyre->fresh();
        });
    }

    /**
     * @return array{summary: array<string, int|float>, history: list<array<string, mixed>>}
     */
    public function vehicleTyreHistory(string $vehicleId): array
    {
        $vehicle = Vehicle::query()->findOrFail($vehicleId);
        $positions = $this->vehiclePositions($vehicleId);

        $installations = TyreInstallation::query()
            ->where('vehicle_id', $vehicleId)
            ->with('tyre')
            ->orderByDesc('installed_at')
            ->orderByDesc('created_at')
            ->get();

        $history = $installations->map(function (TyreInstallation $installation): array {
            $tyre = $installation->tyre;
            $stintKm = $this->lifeCalculator->activeStintKm($installation);

            return [
                'id' => $installation->id,
                'tyre_id' => $installation->tyre_id,
                'tyre_serial_number' => $tyre?->serial_number,
                'tyre_brand' => $tyre?->brand,
                'tyre_status' => $tyre?->status?->value,
                'position' => $installation->position_code,
                'position_label' => $this->positionCatalog->labelFor($installation->position_code),
                'installed_at' => $installation->installed_at?->toDateString(),
                'removed_at' => $installation->removed_at?->toDateString(),
                'km_at_installation' => (float) $installation->km_at_installation,
                'km_at_removal' => $installation->km_at_removal !== null ? (float) $installation->km_at_removal : null,
                'stint_km' => round($stintKm, 2),
                'is_active' => $installation->removed_at === null,
                'removal_recommendation' => $installation->removal_recommendation,
            ];
        })->all();

        $totalKm = $installations->sum(
            fn (TyreInstallation $installation): float => $this->lifeCalculator->activeStintKm($installation)
        );

        return [
            'summary' => [
                'unique_tyres_used' => $installations->pluck('tyre_id')->unique()->count(),
                'total_stints' => $installations->count(),
                'currently_installed' => $installations->whereNull('removed_at')->count(),
                'empty_positions' => collect($positions)->where('is_empty', true)->count(),
                'total_positions' => count($positions),
                'total_km_on_truck' => round($totalKm, 2),
            ],
            'history' => $history,
        ];
    }

    public function vehiclePositions(string $vehicleId): array
    {
        $vehicle = Vehicle::query()->findOrFail($vehicleId);
        $installations = TyreInstallation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNull('removed_at')
            ->with('tyre')
            ->get();

        return collect($this->positionCatalog->positionsForVehicle($vehicle))->map(function (array $slot) use ($installations): array {
            $installation = $installations->first(
                fn (TyreInstallation $row) => (string) $row->position_code === $slot['code']
            );

            $tyre = $installation?->tyre;
            $life = $tyre ? $this->lifeCalculator->summary($tyre) : null;
            $stintKm = $installation ? $this->lifeCalculator->activeStintKm($installation) : 0;

            return [
                'position' => $slot['code'],
                'position_label' => $slot['label'],
                'installation_id' => $installation?->id,
                'is_empty' => $installation === null,
                'is_spare' => $slot['is_spare'],
                'axle' => $slot['axle'],
                'tyre' => $tyre ? [
                    'id' => $tyre->id,
                    'serial_number' => $tyre->serial_number,
                    'brand' => $tyre->brand,
                    'status' => $tyre->status?->value,
                    'installed_at' => $installation->installed_at?->toDateString(),
                    'km_at_installation' => (float) $installation->km_at_installation,
                    'stint_km' => round($stintKm, 2),
                    'remaining_life_km' => $life['remaining_life_km'] ?? null,
                    'life_used_percent' => $life['life_used_percent'] ?? null,
                ] : null,
            ];
        })->all();
    }

    /**
     * @return array{level: string, message: string, remaining_life_km: float, stint_km: float}|null
     */
    public function previewRemoval(TyreInstallation $installation, ?float $stintKm = null, ?float $odometerKm = null): ?array
    {
        $installation->loadMissing(['tyre', 'vehicle']);

        if ($odometerKm !== null) {
            $km = $this->stintCalculator->stintFromInstallationOdometer($installation, $odometerKm);
        } else {
            $km = $stintKm ?? (float) ($installation->stint_km ?? 0);
        }

        return $this->lifeCalculator->removalRecommendation($installation->tyre, max(0, $km));
    }

    public function updateStintKm(TyreInstallation $installation, array $data): TyreInstallation
    {
        return DB::transaction(function () use ($installation, $data): TyreInstallation {
            if ($installation->removed_at !== null) {
                throw ValidationException::withMessages(['installation' => 'Cannot update KM for a removed installation.']);
            }

            $stintKm = $this->stintCalculator->resolveStintForInstallation($installation, $data);
            $odometerReading = $this->stintCalculator->resolveOdometerReading($data);

            $installation->update([
                'stint_km' => $stintKm,
            ]);

            $installation->loadMissing('vehicle');
            if ($installation->vehicle !== null && $odometerReading !== null) {
                $this->stintCalculator->syncVehicleOdometer($installation->vehicle, $odometerReading);
            }

            return $installation->fresh(['vehicle', 'tyre']);
        });
    }

    public function recordMaintenanceExpense(Tyre $tyre, array $data): TyreExpense
    {
        return $this->recordExpense(
            $tyre,
            TyreExpenseType::from($data['expense_type'] ?? TyreExpenseType::Maintenance->value),
            (float) $data['amount'],
            $data['expense_date'],
            $data['notes'] ?? 'Maintenance expense',
        );
    }

    private function assertCurrentKmCanBeSet(Tyre $tyre, float $newValue): void
    {
        if ($tyre->status === TyreStatus::Scrapped) {
            throw ValidationException::withMessages([
                'current_km' => 'Cannot change KM already used on a scrapped tyre.',
            ]);
        }

        $minimumFromStints = (float) $tyre->installations()
            ->whereNotNull('removed_at')
            ->sum('running_km');

        if ($newValue < $minimumFromStints) {
            throw ValidationException::withMessages([
                'current_km' => "KM already used cannot be less than {$minimumFromStints} km recorded from truck usage.",
            ]);
        }
    }

    private function assertPositionAvailable(Vehicle $vehicle, string $positionCode, ?string $exceptInstallationId): void
    {
        if (! $this->positionCatalog->isAllowedOnVehicle($vehicle, $positionCode)) {
            throw ValidationException::withMessages([
                'position_code' => 'This position is not valid for the selected truck layout.',
            ]);
        }

        $query = TyreInstallation::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('position_code', $positionCode)
            ->whereNull('removed_at');

        if ($exceptInstallationId !== null) {
            $query->where('id', '!=', $exceptInstallationId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'position_code' => 'Another tyre is already assigned to this position on the truck.',
            ]);
        }
    }

    private function recordExpense(Tyre $tyre, TyreExpenseType $type, float $amount, string $date, ?string $notes): TyreExpense
    {
        return TyreExpense::query()->create([
            'tyre_id' => $tyre->id,
            'expense_type' => $type->value,
            'amount' => $amount,
            'expense_date' => $date,
            'notes' => $notes,
        ]);
    }
}
