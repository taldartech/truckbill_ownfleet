<?php

namespace Tests\Unit\Support;

use App\Driver;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Support\FleetComplianceAlerts;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetComplianceAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \App\Support\TenantContext::setTenantId(null);
        \App\Support\BranchContext::setBranchId(null);
        parent::tearDown();
    }

    public function test_user_without_permissions_returns_empty_alert_structures(): void
    {
        $user = new User(['user_role_id' => null]);

        $alerts = FleetComplianceAlerts::build($user);

        $this->assertSame([], $alerts['documents']);
        $this->assertSame([], $alerts['licenses']);
        $this->assertSame([], $alerts['due_services']);
        $this->assertSame([], $alerts['eway_bills']);
        $this->assertSame([], $alerts['fuel_alerts']);
        $this->assertSame([], $alerts['alerts']);
    }

    public function test_build_detects_and_normalizes_compliance_alerts(): void
    {
        $this->seedRolesAndPermissions();

        $tenant = Tenant::query()->create([
            'name' => 'Alerts Co',
            'slug' => 'alerts-co-' . uniqid(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $branch = \App\Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Alerts HO',
            'branch_contact_number' => '9876543210',
            'head_office' => true,
            'inherit_company_gst' => true,
        ]);

        \App\Support\TenantContext::setTenantId($tenant->id);
        \App\Support\BranchContext::setBranchId($branch->id);

        $admin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'user_role_id' => UserRole::ADMIN,
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'registration_number' => 'MH12AL1234',
            'vehicle_type' => 'truck',
            'fuel_tank_capacity_ltr' => 200,
            'default_mileage_km_per_liter' => 4.0,
            'is_active' => true,
        ]);

        // 1. Expiring Vehicle Document
        VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'fitness_certificate',
            'expiry_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        // 2. Expiring & Expired Driver Licenses
        $driverExpiring = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Driver Expiring',
            'phone' => '9811111111',
            'license_number' => 'LIC-EXP-1',
            'license_expiry_date' => Carbon::today()->addDays(10)->toDateString(),
            'is_active' => true,
        ]);

        $driverExpired = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Driver Expired',
            'phone' => '9822222222',
            'license_number' => 'LIC-EXP-2',
            'license_expiry_date' => Carbon::today()->subDays(5)->toDateString(),
            'is_active' => true,
        ]);

        // 3. Trip with E-Way Bill expiring soon
        $trip = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverExpiring->id,
            'trip_number' => 'TRIP-AL-001',
            'status' => TripStatus::InProgress->value,
            'start_at' => Carbon::today()->subDays(2),
            'start_meter_reading_km' => 10000,
            'end_meter_reading_km' => 10200, // 200 km
        ]);

        TripRouteLeg::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'trip_id' => $trip->id,
            'sequence_no' => 1,
            'ewaybill_no' => 'EWAY-1234567890',
            'ewaybill_expiry' => Carbon::today()->addHours(6),
        ]);

        // 4. Fuel Anomaly: Overfill (liters > tank capacity)
        TripExpense::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'fuel_liters' => 350, // exceeds 200 capacity
            'amount' => 35000,
            'expense_date' => Carbon::today()->toDateString(),
        ]);

        // 5. Trip with missing odometer readings
        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverExpired->id,
            'trip_number' => 'TRIP-AL-002',
            'status' => TripStatus::Completed->value,
            'start_at' => Carbon::today()->subDays(1),
            'start_meter_reading_km' => null,
            'end_meter_reading_km' => null,
        ]);

        $alerts = FleetComplianceAlerts::build($admin);

        // Verify categorized alerts
        $this->assertNotEmpty($alerts['documents']);
        $this->assertCount(2, $alerts['licenses']);
        $this->assertNotEmpty($alerts['eway_bills']);
        $this->assertNotEmpty($alerts['fuel_alerts']);

        // Verify normalized unified alerts list
        $this->assertNotEmpty($alerts['alerts']);

        $categories = array_column($alerts['alerts'], 'category');
        $this->assertContains('vehicle_documents', $categories);
        $this->assertContains('drivers', $categories);
        $this->assertContains('ewaybill', $categories);
        $this->assertContains('fuel', $categories);
    }
}
