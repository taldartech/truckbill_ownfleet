<?php

namespace Database\Seeders;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Enums\AttendanceMarkedVia;
use App\Enums\AttendanceStatus;
use App\Enums\WagePaymentStatus;
use App\Support\BranchContext;
use App\Support\BranchProvisioner;
use App\Support\TenantContext;
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
     * @var list<array{
     *     name: string,
     *     phone: string,
     *     daily_wage: float,
     *     employee_code: string,
     *     father_name: string,
     *     bank_account_number: string,
     *     bank_ifsc: string
     * }>
     */
    private const DRIVERS = [
        ['name' => 'Ramesh Kumar', 'phone' => '9876501001', 'daily_wage' => 850.00, 'employee_code' => 'DRV-1001', 'father_name' => 'Rajesh Kumar', 'bank_account_number' => '50100100010001', 'bank_ifsc' => 'HDFC0001001'],
        ['name' => 'Suresh Patil', 'phone' => '9876501002', 'daily_wage' => 900.00, 'employee_code' => 'DRV-1002', 'father_name' => 'Ganesh Patil', 'bank_account_number' => '50100100010002', 'bank_ifsc' => 'HDFC0001002'],
        ['name' => 'Vikram Singh', 'phone' => '9876501003', 'daily_wage' => 950.00, 'employee_code' => 'DRV-1003', 'father_name' => 'Harvinder Singh', 'bank_account_number' => '50100100010003', 'bank_ifsc' => 'HDFC0001003'],
        ['name' => 'Anil Sharma', 'phone' => '9876501004', 'daily_wage' => 1000.00, 'employee_code' => 'DRV-1004', 'father_name' => 'Sunil Sharma', 'bank_account_number' => '50100100010004', 'bank_ifsc' => 'HDFC0001004'],
        ['name' => 'Deepak Yadav', 'phone' => '9876501005', 'daily_wage' => 1050.00, 'employee_code' => 'DRV-1005', 'father_name' => 'Ramprasad Yadav', 'bank_account_number' => '50100100010005', 'bank_ifsc' => 'HDFC0001005'],
        ['name' => 'Manoj Gupta', 'phone' => '9876501006', 'daily_wage' => 1100.00, 'employee_code' => 'DRV-1006', 'father_name' => 'Vinod Gupta', 'bank_account_number' => '50100100010006', 'bank_ifsc' => 'HDFC0001006'],
        ['name' => 'Prakash Mehta', 'phone' => '9876501007', 'daily_wage' => 1150.00, 'employee_code' => 'DRV-1007', 'father_name' => 'Mukesh Mehta', 'bank_account_number' => '50100100010007', 'bank_ifsc' => 'HDFC0001007'],
        ['name' => 'Sanjay Reddy', 'phone' => '9876501008', 'daily_wage' => 1200.00, 'employee_code' => 'DRV-1008', 'father_name' => 'Venkat Reddy', 'bank_account_number' => '50100100010008', 'bank_ifsc' => 'HDFC0001008'],
        ['name' => 'Ajay Desai', 'phone' => '9876501009', 'daily_wage' => 925.00, 'employee_code' => 'DRV-1009', 'father_name' => 'Jayesh Desai', 'bank_account_number' => '50100100010009', 'bank_ifsc' => 'HDFC0001009'],
        ['name' => 'Nitin Joshi', 'phone' => '9876501010', 'daily_wage' => 975.00, 'employee_code' => 'DRV-1010', 'father_name' => 'Amit Joshi', 'bank_account_number' => '50100100010010', 'bank_ifsc' => 'HDFC0001010'],
        ['name' => 'Rahul Bench', 'phone' => '9876501011', 'daily_wage' => 900.00, 'employee_code' => 'DRV-1011', 'father_name' => 'Ravi Bench', 'bank_account_number' => '50100100010011', 'bank_ifsc' => 'HDFC0001011'],
        ['name' => 'Kiran Bench', 'phone' => '9876501012', 'daily_wage' => 950.00, 'employee_code' => 'DRV-1012', 'father_name' => 'Sunil Bench', 'bank_account_number' => '50100100010012', 'bank_ifsc' => 'HDFC0001012'],
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
        $this->seedForTenant(self::DEFAULT_TENANT_SLUG);
    }

    public function seedForTenant(
        string $slug,
        ?string $attendanceFrom = null,
        ?string $attendanceTo = null,
    ): void {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->command?->error("Tenant not found for slug: {$slug}");

            return;
        }

        $branch = BranchProvisioner::ensureDefaultBranch($tenant);

        TenantContext::setTenantId($tenant->id);
        BranchContext::setBranchId($branch->id);

        try {
            $from = $attendanceFrom ?? self::ATTENDANCE_FROM;
            $to = $attendanceTo ?? self::ATTENDANCE_TO;

            $drivers = $this->seedDrivers($tenant->id, $branch->id);
            $vehicles = $this->seedVehicles($tenant->id, $branch->id);
            $this->seedAssignments($tenant->id, $branch->id, $drivers, $vehicles, $from);

            $attendanceCount = $this->seedAttendances($tenant->id, $branch->id, $drivers, $vehicles, $from, $to);

            $fromLabel = Carbon::parse($from)->format('M Y');
            $toLabel = Carbon::parse($to)->format('M Y');

            $this->command?->info("Demo fleet data ready for [{$tenant->name}] ({$slug})");
            $this->command?->table(
                ['Item', 'Count'],
                [
                    ['Drivers', (string) $drivers->count()],
                    ['Vehicles', (string) $vehicles->count()],
                    ['Attendance rows ('.$fromLabel.' – '.$toLabel.')', (string) $attendanceCount],
                ],
            );
        } finally {
            BranchContext::setBranchId(null);
            TenantContext::setTenantId(null);
        }
    }

    /**
     * @return Collection<int, Driver>
     */
    private function seedDrivers(string $tenantId, int $branchId): Collection
    {
        $drivers = collect();

        foreach (self::DRIVERS as $index => $definition) {
            $drivers->push(
                Driver::withoutGlobalScope('branch')->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'phone' => $definition['phone'],
                    ],
                    [
                        'branch_id' => $branchId,
                        'name' => $definition['name'],
                        'employee_code' => $definition['employee_code'],
                        'father_name' => $definition['father_name'],
                        'bank_account_number' => $definition['bank_account_number'],
                        'bank_ifsc' => $definition['bank_ifsc'],
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
    private function seedVehicles(string $tenantId, int $branchId): Collection
    {
        $vehicles = collect();

        foreach (self::VEHICLES as $index => $definition) {
            $vehicles->push(
                Vehicle::withoutGlobalScope('branch')->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'registration_number' => $definition['registration_number'],
                    ],
                    [
                        'branch_id' => $branchId,
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
    private function seedAssignments(string $tenantId, int $branchId, Collection $drivers, Collection $vehicles, string $attendanceFrom): void
    {
        $assignedFrom = Carbon::parse($attendanceFrom)->subMonth()->startOfMonth();

        foreach ($vehicles as $index => $vehicle) {
            $driver = $drivers->get($index);

            if ($driver === null) {
                continue;
            }

            DriverAssignment::withoutGlobalScope('branch')->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'is_primary' => true,
                    'assigned_to' => null,
                ],
                [
                    'branch_id' => $branchId,
                    'assigned_from' => $assignedFrom,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedAttendances(
        string $tenantId,
        int $branchId,
        Collection $drivers,
        Collection $vehicles,
        string $attendanceFrom,
        string $attendanceTo,
    ): int {
        $period = CarbonPeriod::create($attendanceFrom, $attendanceTo);
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

                Attendance::withoutGlobalScope('branch')->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'vehicle_id' => $vehicle->id,
                        'attendance_date' => $date->toDateString(),
                    ],
                    [
                        'branch_id' => $branchId,
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
