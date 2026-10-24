<?php

namespace Tests\Unit\Models;

use App\Battery;
use App\BatteryInstallation;
use App\DriverAssignment;
use App\Enums\BatteryStatus;
use App\MaintenanceServiceType;
use App\Support\BranchContext;
use App\Support\MaintenanceDueStatus;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use App\VehicleMaintenance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDocumentAndMaintenanceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_model_relations(): void
    {
        $vehicle = new Vehicle();

        $this->assertInstanceOf(BelongsTo::class, $vehicle->tenant());
        $this->assertInstanceOf(BelongsTo::class, $vehicle->createdBy());
        $this->assertInstanceOf(BelongsTo::class, $vehicle->updatedBy());
        $this->assertInstanceOf(BelongsTo::class, $vehicle->deletedBy());
        $this->assertInstanceOf(HasMany::class, $vehicle->assignments());
        $this->assertInstanceOf(HasOne::class, $vehicle->currentPrimaryAssignment());
        $this->assertInstanceOf(HasMany::class, $vehicle->documents());
        $this->assertInstanceOf(HasMany::class, $vehicle->trips());
        $this->assertInstanceOf(HasMany::class, $vehicle->maintenances());
        $this->assertInstanceOf(HasMany::class, $vehicle->batteryInstallations());
        $this->assertInstanceOf(HasMany::class, $vehicle->activeBatteries());
    }

    public function test_vehicle_active_batteries_filters_removed_and_eager_loads_battery(): void
    {
        $this->seedRolesAndPermissions();

        $tenant = Tenant::query()->create([
            'name' => 'Battery Tenant',
            'slug' => 'battery-tenant',
            'status' => 'active',
        ]);
        $operational = $this->operationalAttributesForTenant($tenant);
        BranchContext::setBranchId((int) $operational['branch_id']);
        TenantContext::setTenantId($tenant->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Deleter User',
            'email' => 'deleter@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::idFor('admin') ?? UserRole::ADMIN,
        ]);
        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'MH12CD1234',
            'vehicle_type' => 'TRUCK',
            'status' => 'ACTIVE',
        ]);

        $this->assertTrue($vehicle->tenant->is($tenant));

        $batteryA = Battery::query()->create([
            ...$operational,
            'serial_number' => 'BAT-UNIT-01',
            'brand' => 'Exide',
            'voltage' => '12V',
            'capacity_ah' => 150,
            'status' => BatteryStatus::Installed->value,
            'purchase_date' => now()->toDateString(),
        ]);
        $batteryB = Battery::query()->create([
            ...$operational,
            'serial_number' => 'BAT-UNIT-02',
            'brand' => 'Amaron',
            'voltage' => '12V',
            'capacity_ah' => 150,
            'status' => BatteryStatus::InStock->value,
            'purchase_date' => now()->toDateString(),
        ]);

        $active = BatteryInstallation::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'battery_id' => $batteryA->id,
            'slot_name' => 'Slot 1',
            'installed_at' => now()->toDateString(),
            'removed_at' => null,
        ]);

        BatteryInstallation::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'battery_id' => $batteryB->id,
            'slot_name' => 'Slot 2',
            'installed_at' => now()->subMonth()->toDateString(),
            'removed_at' => now()->toDateString(),
        ]);

        $this->assertCount(2, $vehicle->batteryInstallations);
        $this->assertCount(1, $vehicle->activeBatteries);
        $this->assertTrue($vehicle->activeBatteries->first()->is($active));
        $this->assertTrue($vehicle->activeBatteries->first()->relationLoaded('battery'));
        $this->assertSame('BAT-UNIT-01', $vehicle->activeBatteries->first()->battery->serial_number);

        $vehicle->update(['deleted_by' => $user->id]);
        $vehicle->delete();
        $trashed = Vehicle::onlyTrashed()->find($vehicle->id);
        $this->assertTrue($trashed->deletedBy->is($user));
    }

    public function test_vehicle_document_model_methods_and_relations(): void
    {
        $doc = new VehicleDocument([
            'file_path' => 'documents/test.pdf',
        ]);

        $this->assertTrue($doc->hasStoredFile());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $doc->vehicle());

        $emptyDoc = new VehicleDocument(['file_path' => null]);
        $this->assertFalse($emptyDoc->hasStoredFile());
    }

    public function test_vehicle_maintenance_model_relations(): void
    {
        $maint = new VehicleMaintenance();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $maint->vehicle());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $maint->maintenanceServiceType());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $maint->createdBy());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $maint->updatedBy());
    }

    public function test_maintenance_due_status_resolver_and_labels(): void
    {
        // 1. None
        $this->assertSame(MaintenanceDueStatus::NONE, MaintenanceDueStatus::resolve(null, null, null));
        $this->assertSame('No Schedule', MaintenanceDueStatus::label(MaintenanceDueStatus::NONE));

        // 2. Due by KM
        $this->assertSame(MaintenanceDueStatus::DUE, MaintenanceDueStatus::resolve(55000, 50000, null));
        $this->assertSame('Due', MaintenanceDueStatus::label(MaintenanceDueStatus::DUE));

        // 3. Due by Date
        $this->assertSame(MaintenanceDueStatus::DUE, MaintenanceDueStatus::resolve(10000, 50000, '2026-01-01', '2026-06-01'));

        // 4. Upcoming
        $this->assertSame(MaintenanceDueStatus::UPCOMING, MaintenanceDueStatus::resolve(10000, 50000, '2026-12-31', '2026-06-01'));
        $this->assertSame('Upcoming', MaintenanceDueStatus::label(MaintenanceDueStatus::UPCOMING));

        // 5. Default label
        $this->assertSame('—', MaintenanceDueStatus::label('unknown'));
    }

    public function test_maintenance_overdue_reasons_and_labels(): void
    {
        // Both
        $this->assertSame('both', MaintenanceDueStatus::overdueReason(60000, 50000, '2026-01-01', '2026-06-01'));
        $this->assertSame('KM & Date', MaintenanceDueStatus::overdueReasonLabel('both'));

        // KM only
        $this->assertSame('km', MaintenanceDueStatus::overdueReason(60000, 50000, '2026-12-31', '2026-06-01'));
        $this->assertSame('KM', MaintenanceDueStatus::overdueReasonLabel('km'));

        // Date only
        $this->assertSame('date', MaintenanceDueStatus::overdueReason(10000, 50000, '2026-01-01', '2026-06-01'));
        $this->assertSame('Date', MaintenanceDueStatus::overdueReasonLabel('date'));

        // Neither
        $this->assertNull(MaintenanceDueStatus::overdueReason(10000, 50000, '2026-12-31', '2026-06-01'));
        $this->assertSame('—', MaintenanceDueStatus::overdueReasonLabel(null));
    }

    public function test_vehicle_document_deleting_hook(): void
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $tenant = Tenant::query()->create([
            'name' => 'Hook Tenant',
            'slug' => 'hook-tenant',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB9999',
            'vehicle_type' => 'TRUCK',
            'status' => 'ACTIVE',
        ]);

        $doc = VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'INSURANCE',
            'file_path' => 'documents/test.pdf',
        ]);

        $doc->delete();
        $this->assertDatabaseMissing('vehicle_documents', ['id' => $doc->id]);
    }
}
