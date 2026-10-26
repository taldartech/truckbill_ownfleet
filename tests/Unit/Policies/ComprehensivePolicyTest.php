<?php

namespace Tests\Unit\Policies;

use App\Attendance;
use App\Battery;
use App\Branch;
use App\Broker;
use App\Challan;
use App\City;
use App\Customer;
use App\DocumentType;
use App\Driver;
use App\DriverAssignment;
use App\Emi;
use App\GoodsType;
use App\Invoice;
use App\MaintenanceServiceType;
use App\Permission;
use App\Policies\AttendancePolicy;
use App\Policies\BatteryPolicy;
use App\Policies\BranchPolicy;
use App\Policies\BrokerPolicy;
use App\Policies\ChallanPolicy;
use App\Policies\CityPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DocumentTypePolicy;
use App\Policies\DriverAssignmentPolicy;
use App\Policies\DriverPolicy;
use App\Policies\EmiPolicy;
use App\Policies\GoodsTypePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MaintenanceServiceTypePolicy;
use App\Policies\ManagedUserPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\StatePolicy;
use App\Policies\StationPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TripExpenseTypePolicy;
use App\Policies\TripPolicy;
use App\Policies\TruckTypePolicy;
use App\Policies\TyreLayoutPolicy;
use App\Policies\TyrePolicy;
use App\Policies\UserRolePolicy;
use App\Policies\VehicleDocumentPolicy;
use App\Policies\VehicleMaintenancePolicy;
use App\Policies\VehiclePolicy;
use App\State;
use App\Station;
use App\Support\BranchContext;
use App\Support\CurrentTenant;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\TripExpenseTypeMaster;
use App\TruckType;
use App\Tyre;
use App\TyreLayout;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use App\VehicleMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComprehensivePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $unprivilegedUser;
    private User $masterAdmin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->tenant = Tenant::query()->create([
            'name' => 'Alpha Transport',
            'slug' => 'alphatrans',
            'status' => 'active',
        ]);

        $adminRoleId = UserRole::idFor('admin');
        $this->admin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_role_id' => $adminRoleId,
            'name' => 'Alpha Admin',
            'email' => 'admin@alpha.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $moderatorRoleId = UserRole::idFor('moderator');
        $this->unprivilegedUser = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_role_id' => $moderatorRoleId,
            'name' => 'Alpha Mod',
            'email' => 'mod@alpha.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $superAdminRoleId = UserRole::idFor('super_admin');
        $this->superAdmin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_role_id' => $superAdminRoleId,
            'name' => 'Alpha Super Admin',
            'email' => 'super@alpha.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $masterAdminRoleId = UserRole::idFor('master_admin');
        $this->masterAdmin = User::query()->create([
            'tenant_id' => $this->tenant->id,
            'user_role_id' => $masterAdminRoleId,
            'name' => 'Master Platform Admin',
            'email' => 'master@platform.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    public function test_emi_policy_all_branches(): void
    {
        $policy = new EmiPolicy();
        $emi = new Emi(['tenant_id' => $this->tenant->id]);
        $otherEmi = new Emi(['tenant_id' => 'other-tenant-uuid']);

        TenantContext::setTenantId($this->tenant->id);

        // Admin checks
        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->view($this->admin, $emi));
        $this->assertTrue($policy->update($this->admin, $emi));

        // Default admin lacks emis.delete
        $this->assertFalse($policy->delete($this->admin, $emi));

        // Grant emis.delete to admin role
        $delPerm = Permission::query()->where('slug', 'emis.delete')->first();
        if ($delPerm) {
            DB::table('permission_user_role')->insertOrIgnore([
                'permission_id' => $delPerm->id,
                'user_role_id' => $this->admin->user_role_id,
            ]);
            $this->admin->refresh();
            $this->admin->clearPermissionCache();
            $this->assertTrue($policy->delete($this->admin, $emi));
        }

        // Mismatched tenant
        $this->assertFalse($policy->view($this->admin, $otherEmi));
        $this->assertFalse($policy->update($this->admin, $otherEmi));
        $this->assertFalse($policy->delete($this->admin, $otherEmi));

        // Moderator checks (lacks manage & view by default)
        $this->assertFalse($policy->viewAny($this->unprivilegedUser));
        $this->assertFalse($policy->create($this->unprivilegedUser));
        $this->assertFalse($policy->view($this->unprivilegedUser, $emi));
        $this->assertFalse($policy->update($this->unprivilegedUser, $emi));
        $this->assertFalse($policy->delete($this->unprivilegedUser, $emi));
    }

    public function test_attendance_policy_all_branches(): void
    {
        $policy = new AttendancePolicy();
        $att = new Attendance(['tenant_id' => $this->tenant->id]);
        $otherAtt = new Attendance(['tenant_id' => 'other-tenant-uuid']);

        TenantContext::setTenantId($this->tenant->id);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->create($this->admin));
        $this->assertTrue($policy->view($this->admin, $att));
        $this->assertTrue($policy->update($this->admin, $att));
        $this->assertTrue($policy->delete($this->admin, $att));

        $this->assertFalse($policy->view($this->admin, $otherAtt));
        $this->assertFalse($policy->update($this->admin, $otherAtt));
        $this->assertFalse($policy->delete($this->admin, $otherAtt));
    }

    public function test_vehicle_and_document_policies(): void
    {
        $vehPolicy = new VehiclePolicy();
        $docPolicy = new VehicleDocumentPolicy();
        $maintPolicy = new VehicleMaintenancePolicy();

        $vehicle = new Vehicle(['tenant_id' => $this->tenant->id]);
        $otherVeh = new Vehicle(['tenant_id' => 'other-tenant']);

        $doc = new VehicleDocument(['tenant_id' => $this->tenant->id]);
        $otherDoc = new VehicleDocument(['tenant_id' => 'other-tenant']);

        $maint = new VehicleMaintenance(['tenant_id' => $this->tenant->id]);
        $otherMaint = new VehicleMaintenance(['tenant_id' => 'other-tenant']);

        TenantContext::setTenantId($this->tenant->id);

        // Vehicle Policy
        $this->assertTrue($vehPolicy->viewAny($this->admin));
        $this->assertTrue($vehPolicy->create($this->admin));
        $this->assertTrue($vehPolicy->view($this->admin, $vehicle));
        $this->assertTrue($vehPolicy->update($this->admin, $vehicle));
        $this->assertTrue($vehPolicy->delete($this->admin, $vehicle));
        $this->assertFalse($vehPolicy->view($this->admin, $otherVeh));

        // Document Policy
        $this->assertTrue($docPolicy->viewAny($this->admin));
        $this->assertTrue($docPolicy->create($this->admin));
        $this->assertTrue($docPolicy->view($this->admin, $doc));
        $this->assertTrue($docPolicy->update($this->admin, $doc));
        $this->assertTrue($docPolicy->delete($this->admin, $doc));
        $this->assertFalse($docPolicy->view($this->admin, $otherDoc));

        // Maintenance Policy
        $this->assertTrue($maintPolicy->viewAny($this->admin));
        $this->assertTrue($maintPolicy->create($this->admin));
        $this->assertTrue($maintPolicy->view($this->admin, $maint));
        $this->assertTrue($maintPolicy->update($this->admin, $maint));
        $this->assertTrue($maintPolicy->delete($this->admin, $maint));
        $this->assertFalse($maintPolicy->view($this->admin, $otherMaint));
    }

    public function test_tyre_and_battery_policies(): void
    {
        $tyrePolicy = new TyrePolicy();
        $layoutPolicy = new TyreLayoutPolicy();
        $battPolicy = new BatteryPolicy();

        $tyre = new Tyre(['tenant_id' => $this->tenant->id]);
        $layout = new TyreLayout(['tenant_id' => $this->tenant->id]);
        $battery = new Battery(['tenant_id' => $this->tenant->id]);

        TenantContext::setTenantId($this->tenant->id);

        $this->assertTrue($tyrePolicy->viewAny($this->admin));
        $this->assertTrue($tyrePolicy->create($this->admin));
        $this->assertTrue($tyrePolicy->view($this->admin, $tyre));
        $this->assertTrue($tyrePolicy->update($this->admin, $tyre));
        $this->assertTrue($tyrePolicy->delete($this->admin, $tyre));

        $this->assertTrue($layoutPolicy->viewAny($this->admin));
        $this->assertTrue($layoutPolicy->create($this->admin));
        $this->assertTrue($layoutPolicy->view($this->admin, $layout));

        $this->assertTrue($battPolicy->viewAny($this->admin));
        $this->assertTrue($battPolicy->create($this->admin));
        $this->assertTrue($battPolicy->view($this->admin, $battery));
    }

    public function test_trip_and_master_data_policies(): void
    {
        $tripPolicy = new TripPolicy();
        $brokerPolicy = new BrokerPolicy();
        $custPolicy = new CustomerPolicy();
        $driverPolicy = new DriverPolicy();
        $assignPolicy = new DriverAssignmentPolicy();

        $trip = new Trip(['tenant_id' => $this->tenant->id]);
        $broker = new Broker(['tenant_id' => $this->tenant->id]);
        $customer = new Customer(['tenant_id' => $this->tenant->id]);
        $driver = new Driver(['tenant_id' => $this->tenant->id]);
        $assignment = new DriverAssignment(['tenant_id' => $this->tenant->id]);

        TenantContext::setTenantId($this->tenant->id);

        $this->assertTrue($tripPolicy->viewAny($this->admin));
        $this->assertTrue($tripPolicy->create($this->admin));
        $this->assertTrue($tripPolicy->view($this->admin, $trip));

        $this->assertTrue($brokerPolicy->viewAny($this->admin));
        $this->assertTrue($brokerPolicy->create($this->admin));
        $this->assertTrue($brokerPolicy->view($this->admin, $broker));

        $this->assertTrue($custPolicy->viewAny($this->admin));
        $this->assertTrue($custPolicy->create($this->admin));
        $this->assertTrue($custPolicy->view($this->admin, $customer));

        $this->assertTrue($driverPolicy->viewAny($this->admin));
        $this->assertTrue($driverPolicy->create($this->admin));
        $this->assertTrue($driverPolicy->view($this->admin, $driver));

        $this->assertTrue($assignPolicy->viewAny($this->admin));
        $this->assertTrue($assignPolicy->create($this->admin));
        $this->assertTrue($assignPolicy->view($this->admin, $assignment));
    }

    public function test_configuration_and_system_policies(): void
    {
        $tenantPolicy = new TenantPolicy();
        $rolePolicy = new UserRolePolicy();
        $permPolicy = new PermissionPolicy();
        $branchPolicy = new BranchPolicy();
        $managedUserPolicy = new ManagedUserPolicy();

        $branch = new Branch(['company_id' => $this->tenant->id]);
        $managedUser = new User(['tenant_id' => $this->tenant->id]);

        TenantContext::setTenantId($this->tenant->id);

        $this->assertFalse($branchPolicy->viewAny($this->admin));
        $this->assertTrue($branchPolicy->viewAny($this->superAdmin));
        $this->assertFalse($branchPolicy->create($this->admin));
        $this->assertTrue($branchPolicy->create($this->superAdmin));

        $this->assertFalse($managedUserPolicy->viewAny($this->admin));
        $this->assertTrue($managedUserPolicy->viewAny($this->superAdmin));
        $this->assertFalse($managedUserPolicy->create($this->admin));
        $this->assertTrue($managedUserPolicy->create($this->superAdmin));

        // UserRolePolicy requires master_admin
        $this->assertFalse($rolePolicy->viewAny($this->admin));
        $this->assertTrue($rolePolicy->viewAny($this->masterAdmin));

        // PermissionPolicy
        $this->assertFalse($permPolicy->viewAny($this->admin));
        $this->assertTrue($permPolicy->viewAny($this->masterAdmin));
    }
}
