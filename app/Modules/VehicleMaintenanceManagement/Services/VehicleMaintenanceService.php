<?php

namespace App\Modules\VehicleMaintenanceManagement\Services;

use App\Modules\VehicleMaintenanceManagement\Repositories\VehicleMaintenanceRepository;
use App\Support\TyreStintCalculator;
use App\VehicleMaintenance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleMaintenanceService
{
    public function __construct(
        private readonly VehicleMaintenanceRepository $repository,
        private readonly TyreStintCalculator $odometerCalculator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): VehicleMaintenance
    {
        return DB::transaction(function () use ($data): VehicleMaintenance {
            $vehicle = $this->repository->findVehicle((string) $data['vehicle_id']);

            if ($vehicle === null) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Selected truck is invalid.',
                ]);
            }

            $maintenance = VehicleMaintenance::query()->create($data);

            $this->odometerCalculator->syncVehicleOdometer($vehicle, (float) $data['odometer_km']);

            return $maintenance->load([
                'vehicle:id,registration_number,is_active',
                'maintenanceServiceType:id,name,code',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(VehicleMaintenance $maintenance, array $data): VehicleMaintenance
    {
        return DB::transaction(function () use ($maintenance, $data): VehicleMaintenance {
            $vehicle = $this->repository->findVehicle((string) ($data['vehicle_id'] ?? $maintenance->vehicle_id));

            if ($vehicle === null) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Selected truck is invalid.',
                ]);
            }

            $maintenance->update($data);

            if (array_key_exists('odometer_km', $data)) {
                $this->odometerCalculator->syncVehicleOdometer($vehicle, (float) $data['odometer_km']);
            }

            return $maintenance->fresh([
                'vehicle:id,registration_number,is_active',
                'maintenanceServiceType:id,name,code',
                'createdBy:id,name',
                'updatedBy:id,name',
            ]);
        });
    }

    public function delete(VehicleMaintenance $maintenance): void
    {
        $maintenance->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, VehicleMaintenance>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * @return LengthAwarePaginator<int, VehicleMaintenance>
     */
    public function listForVehicle(string $vehicleId, int $perPage): LengthAwarePaginator
    {
        return $this->repository->listForVehicle($vehicleId, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, VehicleMaintenance>
     */
    public function dueServices(array $filters = []): Collection
    {
        return $this->repository->dueServices($filters);
    }
}
