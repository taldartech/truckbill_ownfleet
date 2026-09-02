<?php

namespace App\Imports;

use App\Support\CurrentTenant;
use App\TruckType;
use App\TyreLayout;
use App\Vehicle;
use App\Support\VehicleRegistrationNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VehiclesBulkImport implements ToCollection, WithHeadingRow
{
    protected array $result = [
        'total' => 0,
        'successful' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    public function collection(Collection $rows): void
    {
        $this->result['total'] = $rows->count();

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;

            try {
                $data = $this->prepareRow($row->toArray());

                $this->validateRow($data, $excelRow);

                $this->importVehicle($data);

                $this->result['successful']++;
            } catch (ValidationException $e) {
                $this->result['failed']++;

                $this->result['errors'][] = [
                    'row' => $excelRow,
                    'value' => $row['registration_number'] ?? '—',
                    'errors' => $this->extractValidationErrors($e),
                ];
            } catch (\Throwable $e) {
                $this->result['failed']++;

                $this->result['errors'][] = [
                    'row' => $excelRow,
                    'value' => $row['registration_number'] ?? '—',
                    'errors' => [
                        $e->getMessage(),
                    ],
                ];
            }
        }
    }

    protected function prepareRow(array $row): array
    {
        return [
            'registration_number' => $this->cleanValue(
                $row['registration_number'] ?? null
            ),

            'vehicle_type' => $this->cleanValue(
                $row['vehicle_type'] ?? null
            ),

            'tyre_layout' => $this->cleanValue(
                $row['tyre_layout'] ?? null
            ),

            'make' => $this->cleanValue(
                $row['make'] ?? null
            ),

            'model' => $this->cleanValue(
                $row['model'] ?? null
            ),

            'manufacture_year' => $this->cleanValue(
                $row['manufacture_year'] ?? null
            ),

            'odometer_km' => $this->cleanValue(
                $row['odometer_km'] ?? null
            ),

            'default_mileage_km_per_liter' => $this->cleanValue(
                $row['default_mileage_km_per_liter'] ?? null
            ),

            'max_load_ton' => $this->cleanValue(
                $row['max_load_ton'] ?? null
            ),

            'fuel_tank_capacity_ltr' => $this->cleanValue(
                $row['fuel_tank_capacity_ltr'] ?? null
            ),

            'is_active' => $this->parseBoolean(
                $row['is_active'] ?? null
            ),
        ];
    }

    protected function validateRow(array &$data, int $excelRow): void
    {
        $tenantId = CurrentTenant::requireId();

        /*
         * Basic field validation.
         */
        $validator = Validator::make(
            $data,
            [
                'registration_number' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'vehicle_type' => [
                    'required',
                    'string',
                    'max:64',
                ],

                'tyre_layout' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'make' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'model' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'manufacture_year' => [
                    'nullable',
                    'integer',
                    'min:1976',
                    'max:' . date('Y'),
                ],

                'odometer_km' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'default_mileage_km_per_liter' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'max_load_ton' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'fuel_tank_capacity_ltr' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'is_active' => [
                    'required',
                    'boolean',
                ],
            ],
            [
                'registration_number.required' =>
                    'Registration number is required.',

                'vehicle_type.required' =>
                    'Vehicle type is required.',

                'tyre_layout.required' =>
                    'Tyre layout is required.',

                'manufacture_year.min' =>
                    'Manufacture year cannot be before 1976.',

                'manufacture_year.max' =>
                    'Manufacture year cannot be greater than the current year.',

                'is_active.required' =>
                    'is_active must be Yes or No.',

                'is_active.boolean' =>
                    'is_active must be Yes or No.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /*
         * Normalize registration number before checking duplicates.
         */
        $data['registration_number'] = $this->normalizeRegistrationNumber(
            $data['registration_number']
        );

        /*
         * Make sure normalization didn't result in an empty value.
         */
        if ($data['registration_number'] === '') {
            throw ValidationException::withMessages([
                'registration_number' =>
                    'Registration number is invalid.',
            ]);
        }

        /*
         * Validate vehicle type by CODE.
         *
         * The Excel file contains the truck type code.
         */
        $truckType = TruckType::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $data['vehicle_type'])
            ->first();

        if (!$truckType) {
            throw ValidationException::withMessages([
                'vehicle_type' =>
                    "Vehicle type '{$data['vehicle_type']}' is invalid for this company.",
            ]);
        }

        if (!$truckType->is_active) {
            throw ValidationException::withMessages([
                'vehicle_type' =>
                    "Vehicle type '{$data['vehicle_type']}' is inactive.",
            ]);
        }

        /*
         * Validate tyre layout by NAME.
         *
         * The Excel file contains the human-readable tyre layout name.
         */
        $tyreLayout = TyreLayout::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $data['tyre_layout'])
            ->first();

        if (!$tyreLayout) {
            throw ValidationException::withMessages([
                'tyre_layout' =>
                    "Tyre layout '{$data['tyre_layout']}' is invalid.",
            ]);
        }

        /*
         * Layout exists, but it cannot be used if inactive.
         */
        if (!$tyreLayout->is_active) {
            throw ValidationException::withMessages([
                'tyre_layout' =>
                    "Tyre layout '{$data['tyre_layout']}' is inactive.",
            ]);
        }

        /*
         * Convert the human-readable tyre layout NAME
         * into the CODE stored by the vehicles table.
         */
        $data['tyre_layout'] = $tyreLayout->code;

        /*
         * Check for an existing non-deleted vehicle.
         *
         * Vehicle registration numbers are tenant-scoped.
         */
        $existingActiveVehicle = Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->where(
                'registration_number',
                $data['registration_number']
            )
            ->exists();

        if ($existingActiveVehicle) {
            throw ValidationException::withMessages([
                'registration_number' =>
                    "A truck with registration number '{$data['registration_number']}' already exists.",
            ]);
        }
    }

    protected function importVehicle(array $data): void
    {
        $tenantId = CurrentTenant::requireId();
        $userId = auth()->id();

        /*
         * Look for a soft-deleted vehicle with the same
         * registration number.
         */
        $vehicle = Vehicle::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where(
                'registration_number',
                $data['registration_number']
            )
            ->first();

        if ($vehicle) {
            /*
             * Restore the previously deleted vehicle.
             */
            $vehicle->restore();

            $vehicle->update([
                'vehicle_type' => $data['vehicle_type'],
                'tyre_layout' => $data['tyre_layout'],
                'make' => $data['make'],
                'model' => $data['model'],
                'manufacture_year' => $data['manufacture_year'],
                'odometer_km' => $data['odometer_km'],
                'default_mileage_km_per_liter' =>
                    $data['default_mileage_km_per_liter'],
                'max_load_ton' =>
                    $data['max_load_ton'],
                'fuel_tank_capacity_ltr' =>
                    $data['fuel_tank_capacity_ltr'],
                'is_active' => $data['is_active'],
                'updated_by' => $userId,
                'deleted_by' => null,
                'deleted_reason' => null,
            ]);

            return;
        }

        /*
         * Create a new vehicle.
         *
         * BranchScoped will populate branch_id from the
         * current branch context.
         */
        Vehicle::query()->create([
            'tenant_id' => $tenantId,
            'registration_number' =>
                $data['registration_number'],
            'vehicle_type' =>
                $data['vehicle_type'],
            'tyre_layout' =>
                $data['tyre_layout'],
            'make' =>
                $data['make'],
            'model' =>
                $data['model'],
            'manufacture_year' =>
                $data['manufacture_year'],
            'odometer_km' =>
                $data['odometer_km'],
            'default_mileage_km_per_liter' =>
                $data['default_mileage_km_per_liter'],
            'max_load_ton' =>
                $data['max_load_ton'],
            'fuel_tank_capacity_ltr' =>
                $data['fuel_tank_capacity_ltr'],
            'is_active' =>
                $data['is_active'],
            'created_by' =>
                $userId,
            'updated_by' =>
                $userId,
        ]);
    }

    protected function parseBoolean(mixed $value): ?bool
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = strtolower(trim((string) $value));

        return match ($value) {
            'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => null,
        };
    }

    protected function cleanValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    protected function normalizeRegistrationNumber(?string $value): string
    {
        return VehicleRegistrationNumber::normalize($value) ?? '';
    }

    protected function extractValidationErrors(
        ValidationException $exception
    ): array {
        return collect($exception->errors())
            ->flatten()
            ->values()
            ->all();
    }

    public function result(): array
    {
        return $this->result;
    }
}