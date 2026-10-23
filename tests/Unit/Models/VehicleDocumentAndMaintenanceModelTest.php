<?php

namespace Tests\Unit\Models;

use App\MaintenanceServiceType;
use App\Support\MaintenanceDueStatus;
use App\Tenant;
use App\User;
use App\Vehicle;
use App\VehicleDocument;
use App\VehicleMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleDocumentAndMaintenanceModelTest extends TestCase
{
    use RefreshDatabase;

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
