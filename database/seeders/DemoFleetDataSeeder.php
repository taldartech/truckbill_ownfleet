<?php

namespace Database\Seeders;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Enums\AttendanceMarkedVia;
use App\Enums\AttendanceStatus;
use App\Enums\WagePaymentStatus;
use App\Tenant;
use App\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoFleetDataSeeder extends Seeder
{
    public const DEFAULT_TENANT_SLUG = 'test-company';

    private const ATTENDANCE_YEAR = 2026;

    private const ATTENDANCE_FROM = '2026-01-01';

    private const ATTENDANCE_TO = '2026-06-30';

    /**
     * @var list<array{name: string, phone: string, daily_wage: float}>
     */
    private const DRIVERS = [
        ['name' => 'Ramesh Kumar', 'phone' => '9876501001', 'daily_wage' => 850.00],
        ['name' => 'Suresh Patil', 'phone' => '9876501002', 'daily_wage' => 900.00],
        ['name' => 'Vikram Singh', 'phone' => '9876501003', 'daily_wage' => 950.00],
        ['name' => 'Anil Sharma', 'phone' => '9876501004', 'daily_wage' => 1000.00],
        ['name' => 'Deepak Yadav', 'phone' => '9876501005', 'daily_wage' => 1050.00],
        ['name' => 'Manoj Gupta', 'phone' => '9876501006', 'daily_wage' => 1100.00],
        ['name' => 'Prakash Mehta', 'phone' => '9876501007', 'daily_wage' => 1150.00],
        ['name' => 'Sanjay Reddy', 'phone' => '9876501008', 'daily_wage' => 1200.00],
        ['name' => 'Ajay Desai', 'phone' => '9876501009', 'daily_wage' => 925.00],
        ['name' => 'Nitin Joshi', 'phone' => '9876501010', 'daily_wage' => 975.00],
    ];

    /**
     * @var list<array{registration_number: string, make: string, model: string}>
     */
    private const VEHICLES = [
        ['registration_number' => 'MH12AB1001', 'make' => 'Tata', 'model' => 'LPT 407'],
        ['registration_number' => 'MH12AB1002', 'make' => 'Ashok Leyland', 'model' => 'Boss 1215'],
        ['registration_number' => 'MH12AB1003', 'make' => 'Mahindra', 'model' => 'Blazo X 28'],
        ['registration_number' => 'MH12AB1004', 'make' => 'Eicher', 'model' => 'Pro 2049'],
        ['registration_number' => 'MH12AB1005', 'make' => 'Tata', 'model' => 'Signa 2823'],
        ['registration_number' => 'MH12AB1006', 'make' => 'BharatBenz', 'model' => '1214R'],
        ['registration_number' => 'MH12AB1007', 'make' => 'Ashok Leyland', 'model' => 'Partner 6 Tyre'],
        ['registration_number' => 'MH12AB1008', 'make' => 'Tata', 'model' => 'Ultra T.7'],
        ['registration_number' => 'MH12AB1009', 'make' => 'Mahindra', 'model' => 'Furio 7'],
        ['registration_number' => 'MH12AB1010', 'make' => 'Eicher', 'model' => 'Pro 3015'],
    ];

    public function run(): void
    {
        $slug = self::DEFAULT_TENANT_SLUG;

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->command?->error("Tenant not found for slug: {$slug}");

            return;
        }

        $drivers = $this->seedDrivers($tenant->id);
        $vehicles = $this->seedVehicles($tenant->id);
        $this->seedAssignments($tenant->id, $drivers, $vehicles);

        $attendanceCount = $this->seedAttendances($tenant->id, $drivers, $vehicles);

        $this->command?->info("Demo fleet data ready for [{$tenant->name}] ({$slug})");
        $this->command?->table(
            ['Item', 'Count'],
            [
                ['Drivers', (string) $drivers->count()],
                ['Vehicles', (string) $vehicles->count()],
                ['Attendance rows (Jan–Jun '.self::ATTENDANCE_YEAR.')', (string) $attendanceCount],
            ],
        );
    }

    /**
     * @return Collection<int, Driver>
     */
    private function seedDrivers(string $tenantId): Collection
    {
        $drivers = collect();

        foreach (self::DRIVERS as $index => $definition) {
            $drivers->push(
                Driver::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'phone' => $definition['phone'],
                    ],
                    [
                        'name' => $definition['name'],
                        'license_number' => 'DL-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                        'license_expiry_date' => Carbon::create(self::ATTENDANCE_YEAR, 12, 31),
                        'joining_date' => Carbon::create(self::ATTENDANCE_YEAR - 1, 6, 1)->addMonths($index),
                        'daily_wage' => $definition['daily_wage'],
                        'is_active' => true,
                    ],
                ),
            );
        }

        return $drivers->values();
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function seedVehicles(string $tenantId): Collection
    {
        $vehicles = collect();

        foreach (self::VEHICLES as $index => $definition) {
            $vehicles->push(
                Vehicle::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'registration_number' => $definition['registration_number'],
                    ],
                    [
                        'vehicle_type' => 'TRUCK',
                        'make' => $definition['make'],
                        'model' => $definition['model'],
                        'manufacture_year' => 2018 + ($index % 6),
                        'odometer_km' => 45000 + ($index * 3200),
                    ],
                ),
            );
        }

        return $vehicles->values();
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedAssignments(string $tenantId, Collection $drivers, Collection $vehicles): void
    {
        $assignedFrom = Carbon::parse(self::ATTENDANCE_FROM)->subMonth()->startOfMonth();

        foreach ($vehicles as $index => $vehicle) {
            $driver = $drivers->get($index);

            if ($driver === null) {
                continue;
            }

            DriverAssignment::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'is_primary' => true,
                    'assigned_to' => null,
                ],
                [
                    'assigned_from' => $assignedFrom,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedAttendances(string $tenantId, Collection $drivers, Collection $vehicles): int
    {
        $period = CarbonPeriod::create(self::ATTENDANCE_FROM, self::ATTENDANCE_TO);
        $created = 0;

        foreach ($vehicles as $index => $vehicle) {
            $driver = $drivers->get($index);

            if ($driver === null) {
                continue;
            }

            foreach ($period as $date) {
                if ($date->isSunday()) {
                    continue;
                }

                $status = $this->resolveStatus($vehicle->id, $date);
                $wageAmount = $status === AttendanceStatus::Present
                    ? (float) $driver->daily_wage
                    : 0.0;

                $payment = $this->resolveWagePayment($date, $wageAmount, $vehicle->id);

                Attendance::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'vehicle_id' => $vehicle->id,
                        'attendance_date' => $date->toDateString(),
                    ],
                    [
                        'primary_driver_id' => $driver->id,
                        'wage_owner_driver_id' => $driver->id,
                        'temporary_driver_id' => null,
                        'temporary_assignment_remarks' => null,
                        'status' => $status->value,
                        'marked_via' => AttendanceMarkedVia::AdminPanel->value,
                        'wage_amount' => $wageAmount,
                        'wage_payment_status' => $payment['status']->value,
                        'wage_paid_at' => $payment['paid_at'],
                        'wage_transfer_notes' => $payment['notes'],
                    ],
                );

                $created++;
            }
        }

        return $created;
    }

    private function resolveStatus(string $vehicleId, Carbon $date): AttendanceStatus
    {
        $bucket = crc32($vehicleId.'|'.$date->toDateString()) % 100;

        return $bucket < 12 ? AttendanceStatus::Absent : AttendanceStatus::Present;
    }

    /**
     * @return array{status: WagePaymentStatus, paid_at: ?Carbon, notes: ?string}
     */
    private function resolveWagePayment(Carbon $date, float $wageAmount, string $vehicleId): array
    {
        if ($wageAmount <= 0) {
            return [
                'status' => WagePaymentStatus::Unpaid,
                'paid_at' => null,
                'notes' => null,
            ];
        }

        $month = (int) $date->format('n');
        $bucket = crc32($vehicleId.'|wage|'.$date->toDateString()) % 100;

        $paid = match (true) {
            $month <= 3 => true,
            $month === 4 => $bucket < 75,
            $month === 5 => $bucket < 45,
            default => $bucket < 15,
        };

        if (! $paid) {
            return [
                'status' => WagePaymentStatus::Unpaid,
                'paid_at' => null,
                'notes' => null,
            ];
        }

        return [
            'status' => WagePaymentStatus::Paid,
            'paid_at' => $date->copy()->addDays(2)->setTime(18, 0),
            'notes' => 'Demo wage transfer',
        ];
    }
}
