<?php

namespace Tests\Feature\Api;

use App\Enums\VehicleDocumentType;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        Storage::fake('fleet_documents');
        config(['fleet-documents.disk' => 'fleet_documents']);
    }

    public function test_document_file_is_stored_under_tenant_vehicle_path(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf');

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/vehicle-documents', [
                'vehicle_id' => $vehicle->id,
                'document_type' => VehicleDocumentType::Insurance->value,
                'issue_date' => now()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => $file,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.has_file', true);
        $response->assertJsonPath('data.original_filename', 'insurance.pdf');

        $documentId = $response->json('data.id');
        $path = \App\VehicleDocument::query()->findOrFail($documentId)->file_path;

        $this->assertStringStartsWith("{$tenant->id}/vehicles/{$vehicle->id}/insurance/", $path);
        Storage::disk('fleet_documents')->assertExists($path);

        $download = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get("/api/vehicle-documents/{$documentId}/download");

        $download->assertOk();
    }

    public function test_deleting_document_removes_file_from_disk(): void
    {
        [$tenant, $user] = $this->createTenantUser('FLEET_MANAGER');
        $vehicle = $this->createVehicle($tenant);

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('rc.pdf', 50, 'application/pdf');

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->post('/api/vehicle-documents', [
                'vehicle_id' => $vehicle->id,
                'document_type' => VehicleDocumentType::Rc->value,
                'file' => $file,
            ]);

        $id = $create->json('data.id');
        $path = \App\VehicleDocument::query()->findOrFail($id)->file_path;

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->delete("/api/vehicle-documents/{$id}")
            ->assertNoContent();

        Storage::disk('fleet_documents')->assertMissing($path);
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

    private function createVehicle(Tenant $tenant): Vehicle
    {
        return Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB1234',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 0,
        ]);
    }
}
