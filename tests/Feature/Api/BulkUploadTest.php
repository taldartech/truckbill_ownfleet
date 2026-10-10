<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\City;
use App\Customer;
use App\Models\TenantSubscription;
use App\Station;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_template_download_streams_csv_headers(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->get('/api/stations/bulk-upload/template');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Station Name', $content);
        $this->assertStringContainsString('State', $content);
        $this->assertStringContainsString('City', $content);
    }

    public function test_stations_bulk_upload_creates_records_successfully(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $city = City::query()
            ->whereHas('state', fn ($query) => $query->where('code', 'MH'))
            ->where('name', 'Mumbai')
            ->firstOrFail();

        $csvContent = "Station Name,State,City\n"
            . "Andheri Hub,Maharashtra,Mumbai\n"
            . "Bandra Hub,Maharashtra,Mumbai\n";

        $file = UploadedFile::fake()->createWithContent('stations.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/stations/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.successful', 2)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('stations', [
            'tenant_id' => $tenant->id,
            'name' => 'Andheri Hub',
            'city_id' => $city->id,
        ]);

        $this->assertDatabaseHas('stations', [
            'tenant_id' => $tenant->id,
            'name' => 'Bandra Hub',
            'city_id' => $city->id,
        ]);
    }

    public function test_bulk_upload_reports_row_validation_errors(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        // Row 1 is missing Station Name, Row 2 has unknown state
        $csvContent = "Station Name,State,City\n"
            . ",Maharashtra,Mumbai\n"
            . "Invalid Hub,NonExistentState,NowhereCity\n";

        $file = UploadedFile::fake()->createWithContent('stations.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/stations/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.successful', 0)
            ->assertJsonPath('summary.failed', 2);

        $errors = $response->json('errors');
        $this->assertCount(2, $errors);
        $this->assertEquals(2, $errors[0]['row']); // row 2 in file
        $this->assertEquals(3, $errors[1]['row']); // row 3 in file
    }

    public function test_bulk_upload_prevents_duplicate_entries_in_same_file_and_database(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $city = City::query()
            ->whereHas('state', fn ($query) => $query->where('code', 'MH'))
            ->where('name', 'Mumbai')
            ->firstOrFail();

        // Existing station in DB
        Station::query()->create([
            'tenant_id' => $tenant->id,
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'name' => 'Existing Depot',
        ]);

        // CSV has one that already exists in DB, and duplicate rows within the file
        $csvContent = "Station Name,State,City\n"
            . "Existing Depot,Maharashtra,Mumbai\n"
            . "Unique Hub,Maharashtra,Mumbai\n"
            . "Unique Hub,Maharashtra,Mumbai\n";

        $file = UploadedFile::fake()->createWithContent('stations.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/stations/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.failed', 2);
    }

    public function test_customer_bulk_upload_with_phone_and_gst_normalization(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $city = City::query()
            ->whereHas('state', fn ($query) => $query->where('code', 'MH'))
            ->where('name', 'Mumbai')
            ->firstOrFail();

        $csvContent = "Customer Name,Mobile,State,City,Address,GST Number\n"
            . "Acme Logistics,9876543210,Maharashtra,Mumbai,Nariman Point,27AAACL1234A1Z5\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/customers/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'name' => 'Acme Logistics',
            'contact_number' => '9876543210',
            'city_id' => $city->id,
            'gst_number' => '27AAACL1234A1Z5',
        ]);
    }

    public function test_goods_type_bulk_upload_creates_records(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $csvContent = "Goods Type Name,Code\n"
            . "Steel Coils,STL-01\n"
            . "Cement Bags,CMT-02\n";

        $file = UploadedFile::fake()->createWithContent('goods_types.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/goods-types/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.successful', 2)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('goods_types', [
            'tenant_id' => $tenant->id,
            'name' => 'Steel Coils',
            'code' => 'STL-01',
        ]);
    }

    public function test_broker_bulk_upload_with_state_and_city_resolution(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $city = City::query()
            ->whereHas('state', fn ($query) => $query->where('code', 'MH'))
            ->where('name', 'Mumbai')
            ->firstOrFail();

        $csvContent = "Reference Name,Mobile,State,City,Address\n"
            . "Rajesh Roadways,9123456789,Maharashtra,Mumbai,Transport Nagar\n";

        $file = UploadedFile::fake()->createWithContent('brokers.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/brokers/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('brokers', [
            'tenant_id' => $tenant->id,
            'name' => 'Rajesh Roadways',
            'contact_number' => '9123456789',
            'city_id' => $city->id,
        ]);
    }

    public function test_driver_bulk_upload_creates_records(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        $csvContent = "Driver Name,Mobile,License Number,Joining Date,Daily Wage\n"
            . "Ramesh Kumar,9811122233,DL1420110012345,2025-01-01,800\n";

        $file = UploadedFile::fake()->createWithContent('drivers.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/drivers/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $tenant->id,
            'name' => 'Ramesh Kumar',
            'phone' => '9811122233',
            'license_number' => 'DL1420110012345',
        ]);
    }

    public function test_vehicle_bulk_upload_creates_records(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('admin');

        \App\Support\TyreLayoutDefaults::seedForTenant($tenant->id);
        \App\Support\TruckTypeDefaults::seedForTenant($tenant->id);

        $truckType = \App\TruckType::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $csvContent = "Vehicle Number,Truck Type\n"
            . "MH12AB1234," . $truckType->name . "\n";

        $file = UploadedFile::fake()->createWithContent('vehicles.csv', $csvContent);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/vehicles/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('summary.successful', 1)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12AB1234',
            'vehicle_type' => $truckType->code,
        ]);
    }

    public function test_unauthorized_user_is_forbidden_from_bulk_upload(): void
    {
        [$tenant, $user, $branchId] = $this->createTenantAndUser('accountant');

        $file = UploadedFile::fake()->createWithContent('customers.csv', "Customer Name,Mobile,State,City\nTest,9999999999,Maharashtra,Mumbai\n");

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($branchId));

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId)
            ->post('/api/customers/bulk-upload', [
                'file' => $file,
            ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User, 2: int}
     */
    private function createTenantAndUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Bulk Upload Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        $operationalAttributes = $this->operationalAttributesForTenant($tenant);
        $branchId = $operationalAttributes['branch_id'];

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $user, $branchId];
    }
}
