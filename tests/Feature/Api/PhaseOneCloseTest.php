<?php

namespace Tests\Feature\Api;

use App\AppNotification;
use App\Attendance;
use App\DocumentAlertSetting;
use App\Driver;
use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Enums\TyreStatus;
use App\Mail\DocumentExpiryReminderMail;
use App\Support\DocumentAlertNotifier;
use App\Tenant;
use App\Trip;
use App\Tyre;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PhaseOneCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_fleet_dashboard_stats_return_live_counts_for_admin(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Dash Driver', 800);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'PRESENT',
            'wage_amount' => 800,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('fleet.vehicles_count', 1)
            ->assertJsonPath('fleet.active_vehicles_count', 1)
            ->assertJsonPath('fleet.drivers_count', 1)
            ->assertJsonPath('fleet.present_today_count', 1)
            ->assertJsonPath('fleet.absent_today_count', 0)
            ->assertJsonPath('fleet.assigned_trucks_today_count', 1);
    }

    public function test_attendance_dashboard_stats_include_all_status_breakdowns(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Primary Driver', 800);
        $substitute = $this->createDriver($tenant, 'Substitute Driver', 600);
        $today = now()->toDateString();

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => $today,
            'is_primary' => true,
        ]);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'temporary_driver_id' => $substitute->id,
            'wage_owner_driver_id' => $substitute->id,
            'attendance_date' => $today,
            'status' => 'ABSENT',
            'wage_amount' => 600,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('fleet.assigned_trucks_today_count', 1)
            ->assertJsonPath('fleet.present_today_count', 0)
            ->assertJsonPath('fleet.absent_today_count', 0)
            ->assertJsonPath('fleet.absent_substitute_today_count', 1)
            ->assertJsonPath('fleet.exceptions_today_count', 1);
    }

    public function test_moderator_cannot_access_attendance_reports(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');

        $this->actingAs($user)
            ->getJson('/app/api/reports/attendance')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/app/api/reports/attendance/export')
            ->assertForbidden();
    }

    public function test_admin_can_export_attendance_report_csv(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Export Driver', 700);

        Attendance::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $driver->id,
            'wage_owner_driver_id' => $driver->id,
            'attendance_date' => '2026-05-01',
            'status' => 'PRESENT',
            'wage_amount' => 700,
            'wage_payment_status' => 'unpaid',
            'marked_via' => 'admin_panel',
        ]);

        $response = $this->actingAs($user)
            ->get('/app/api/reports/attendance/export?from_date=2026-05-01&to_date=2026-05-31');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export Driver', $response->streamedContent());
    }

    public function test_driver_assignments_can_be_filtered_by_vehicle_on_app_api(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicleA = $this->createVehicle($tenant, 'MH01AB1234');
        $vehicleB = $this->createVehicle($tenant, 'MH02CD5678');
        $driver = $this->createDriver($tenant, 'Assigned Driver', 600);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->subMonth()->toDateString(),
            'is_primary' => true,
        ]);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->subMonth()->toDateString(),
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/app/api/driver-assignments?vehicle_id={$vehicleA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_vehicle_list_includes_current_primary_driver(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Primary On Truck', 650);

        DriverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/app/api/vehicles')
            ->assertOk()
            ->assertJsonPath('data.0.current_primary_assignment.driver.name', 'Primary On Truck');
    }

    public function test_moderator_cannot_create_driver_assignment(): void
    {
        [$tenant, $user] = $this->createTenantUser('moderator');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Target Driver', 500);

        $this->actingAs($user)
            ->postJson('/app/api/driver-assignments', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'assigned_from' => now()->toDateString(),
                'is_primary' => true,
            ])
            ->assertForbidden();
    }

    public function test_expiring_documents_count_appears_for_roles_with_document_view(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('fleet.expiring_documents_count', 1)
            ->assertJsonPath('fleet.expired_documents_count', 0);
    }

    public function test_expired_documents_count_appears_for_roles_with_document_view(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'expiry_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('fleet.expired_documents_count', 1)
            ->assertJsonPath('fleet.expiring_documents_count', 0);
    }

    public function test_trip_dashboard_stats_return_status_counts_for_selected_period(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Trip Driver', 900);
        $today = now()->toDateString();

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-001',
            'status' => TripStatus::Created,
            'start_at' => $today.' 08:00:00',
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-002',
            'status' => TripStatus::InProgress,
            'start_at' => $today.' 09:00:00',
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-003',
            'status' => TripStatus::Completed,
            'start_at' => $today.' 10:00:00',
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-004',
            'status' => TripStatus::Cancelled,
            'start_at' => now()->subDays(10)->toDateString().' 11:00:00',
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats?from_date='.$today.'&to_date='.$today)
            ->assertOk()
            ->assertJsonPath('trips.total_count', 3)
            ->assertJsonPath('trips.created_count', 1)
            ->assertJsonPath('trips.in_progress_count', 1)
            ->assertJsonPath('trips.completed_count', 1)
            ->assertJsonPath('trips.cancelled_count', 0)
            ->assertJsonPath('period.from_date', $today)
            ->assertJsonPath('period.to_date', $today);
    }

    public function test_tyre_dashboard_stats_return_status_counts_for_admin(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-DASH-1',
            'status' => TyreStatus::InStock,
            'purchase_cost' => 10000,
        ]);

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-DASH-2',
            'status' => TyreStatus::Installed,
            'purchase_cost' => 12000,
        ]);

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-DASH-3',
            'status' => TyreStatus::Scrapped,
            'purchase_cost' => 9000,
            'scrapped_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('tyres.total_count', 3)
            ->assertJsonPath('tyres.in_stock_count', 1)
            ->assertJsonPath('tyres.installed_count', 1)
            ->assertJsonPath('tyres.scrapped_count', 1);
    }

    public function test_dashboard_stats_include_running_trips_list(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Running Driver', 900);
        $today = now()->toDateString();

        $runningTrip = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-RUN-1',
            'status' => TripStatus::InProgress,
            'start_at' => $today.' 08:00:00',
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-DONE-1',
            'status' => TripStatus::Completed,
            'start_at' => $today.' 09:00:00',
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('trips.running_trips.0.id', $runningTrip->id)
            ->assertJsonPath('trips.running_trips.0.trip_number', 'TRP-RUN-1')
            ->assertJsonPath('trips.running_trips.0.vehicle_registration', $vehicle->registration_number)
            ->assertJsonCount(1, 'trips.running_trips');
    }

    public function test_dashboard_stats_return_trip_financial_totals_for_selected_period(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant, 'Finance Driver', 900);
        $today = now()->toDateString();

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-FIN-1',
            'status' => TripStatus::Completed,
            'start_at' => $today.' 08:00:00',
            'total_freight_amount' => 50000,
            'total_expense_amount' => 20000,
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-FIN-2',
            'status' => TripStatus::InProgress,
            'start_at' => $today.' 09:00:00',
            'total_freight_amount' => 10000,
            'total_expense_amount' => 5000,
        ]);

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => 'TRP-FIN-3',
            'status' => TripStatus::Cancelled,
            'start_at' => $today.' 10:00:00',
            'total_freight_amount' => 8000,
            'total_expense_amount' => 3000,
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats?from_date='.$today.'&to_date='.$today)
            ->assertOk()
            ->assertJsonPath('trips.total_count', 3)
            ->assertJsonPath('trips.total_freight_amount', 50000)
            ->assertJsonPath('trips.total_expense_amount', 20000)
            ->assertJsonPath('trips.profit_amount', 30000);
    }

    public function test_dashboard_stats_return_tyre_period_totals_for_selected_period(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $today = now()->toDateString();

        $purchasedTyre = Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-PERIOD-1',
            'status' => TyreStatus::InStock,
            'purchase_date' => $today,
            'purchase_cost' => 15000,
        ]);

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-PERIOD-2',
            'status' => TyreStatus::Scrapped,
            'purchase_date' => $today,
            'purchase_cost' => 12000,
            'scrapped_at' => $today,
            'scrap_sale_amount' => 2500,
        ]);

        \App\TyreExpense::query()->create([
            'tenant_id' => $tenant->id,
            'tyre_id' => $purchasedTyre->id,
            'expense_type' => \App\Enums\TyreExpenseType::Repair,
            'amount' => 1200,
            'expense_date' => $today,
        ]);

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats?from_date='.$today.'&to_date='.$today)
            ->assertOk()
            ->assertJsonPath('tyres.purchased_count', 2)
            ->assertJsonPath('tyres.purchased_total_cost', 27000)
            ->assertJsonPath('tyres.expenses_total', 1200)
            ->assertJsonPath('tyres.period_scrapped_count', 1)
            ->assertJsonPath('tyres.period_scrapped_recovery_amount', 2500);
    }

    public function test_compliance_alerts_list_expired_and_expiring_documents(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'expiry_date' => now()->subDays(3)->toDateString(),
        ]);

        VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'puc',
            'expiry_date' => now()->addDays(12)->toDateString(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/app/dashboard/compliance-alerts')
            ->assertOk()
            ->assertJsonCount(2, 'documents');

        $documents = collect($response->json('documents'));
        $this->assertTrue($documents->contains(fn (array $item): bool => $item['status'] === 'expired'));
        $this->assertTrue($documents->contains(fn (array $item): bool => $item['status'] === 'expiring'));
    }

    public function test_document_alert_notifier_creates_live_and_email_notifications(): void
    {
        Mail::fake();

        [$tenant, $user] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        $user->update([
            'document_alerts_live' => true,
            'document_alerts_email' => true,
        ]);

        DocumentAlertSetting::query()->create([
            'tenant_id' => $tenant->id,
            'notify_live' => true,
            'notify_email' => true,
            'recipient_user_ids' => [],
        ]);

        $document = VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);

        app(DocumentAlertNotifier::class)->notifyForDocument($document);

        $this->assertDatabaseHas('app_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'document_expiry',
        ]);

        Mail::assertSent(DocumentExpiryReminderMail::class, function (DocumentExpiryReminderMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    public function test_super_admin_can_update_document_alert_settings_per_user(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Alert Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'owner_user_id' => $superAdmin->id,
        ]);

        $companyUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ops User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::MODERATOR,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies/'.$tenant->id.'/enter')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->putJson('/app/api/document-alert-settings', [
                'notify_live' => true,
                'notify_email' => false,
            ])
            ->assertOk()
            ->assertJsonPath('settings.notify_email', false);

        $this->actingAs($superAdmin)
            ->putJson('/app/api/managed-users/'.$companyUser->id, [
                'document_alerts_live' => true,
                'document_alerts_email' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.document_alerts_live', true)
            ->assertJsonPath('data.document_alerts_email', true);

        AppNotification::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $companyUser->id,
            'type' => 'document_expiry',
            'title' => 'Insurance expiring — MH12AB1234',
            'body' => 'Insurance for MH12AB1234 expires soon.',
            'data' => ['status' => 'expiring'],
        ]);

        $this->actingAs($companyUser)
            ->getJson('/app/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->actingAs($companyUser)
            ->getJson('/app/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'document_expiry');
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.fake()->company(),
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $user];
    }

    private function createVehicle(Tenant $tenant, ?string $registration = null): Vehicle
    {
        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => $registration ?? strtoupper(fake()->bothify('??##??####')),
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 1000,
        ]);
    }

    private function createDriver(Tenant $tenant, string $name, float $dailyWage): Driver
    {
        return Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => fake()->numerify('9#########'),
            'license_number' => strtoupper(fake()->bothify('DL-#####')),
            'license_expiry_date' => now()->addYear()->toDateString(),
            'daily_wage' => $dailyWage,
            'is_active' => true,
        ]);
    }
}
