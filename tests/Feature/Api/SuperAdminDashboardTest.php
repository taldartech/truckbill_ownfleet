<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\Challan;
use App\City;
use App\Customer;
use App\Driver;
use App\Emi;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Invoice;
use App\InvoicePayment;
use App\Station;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use App\VehicleMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        Carbon::setTestNow('2026-09-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_sees_group_dashboard_across_owned_companies_only(): void
    {
        [$superAdmin, $alpha, $beta] = $this->createPortfolio();

        $response = $this->actingAs($superAdmin)
            ->getJson('/app/dashboard/group?from_date=2026-09-01&to_date=2026-09-15')
            ->assertOk();

        $response
            ->assertJsonPath('period.previous_from_date', '2026-08-01')
            ->assertJsonPath('period.previous_to_date', '2026-08-15')
            ->assertJsonCount(2, 'filters.companies')
            ->assertJsonPath('executive.companies_count', 2)
            ->assertJsonPath('executive.revenue.current', 80000)
            ->assertJsonPath('executive.revenue.previous', 40000)
            ->assertJsonPath('executive.revenue.change_percent', 100)
            ->assertJsonPath('executive.expenses.current', 30000)
            ->assertJsonPath('executive.profit.current', 50000)
            ->assertJsonPath('executive.margin.current', 62.5)
            ->assertJsonPath('fleet.overview', [
                'total_trucks' => 4,
                'on_trip' => 1,
                'available' => 1,
                'idle' => 1,
                'maintenance' => 0,
                'unavailable' => 1,
            ])
            ->assertJsonPath('fleet.utilization.available_days', 45)
            ->assertJsonPath('fleet.utilization.running_days', 6)
            ->assertJsonPath('fleet.utilization.rate', 13.3)
            ->assertJsonPath('fleet.idle.over_warning_days', 1)
            ->assertJsonPath('fleet.idle.over_critical_days', 1)
            ->assertJsonPath('fleet.idle.vehicles.0.registration_number', 'MH12IDLE01')
            ->assertJsonPath('fleet.idle.vehicles.0.company_name', 'Alpha Logistics')
            ->assertJsonPath('fleet.on_trip_by_company.0.company_name', 'Alpha Logistics')
            ->assertJsonPath('fleet.on_trip_by_company.0.count', 1)
            ->assertJsonPath('activity.trips.total', 4)
            ->assertJsonPath('activity.trips.completed', 2)
            ->assertJsonPath('activity.trips.in_progress', 1)
            ->assertJsonPath('activity.trips.cancelled', 1)
            ->assertJsonPath('activity.invoices_count', 2)
            ->assertJsonPath('activity.payments_count', 1)
            ->assertJsonCount(12, 'trend')
            ->assertJsonPath('trend.11.month', '2026-09')
            ->assertJsonPath('trend.11.revenue', 80000)
            ->assertJsonPath('trend.10.revenue', 40000)
            ->assertJsonPath('companies.0.name', 'Alpha Logistics')
            ->assertJsonPath('companies.0.trips_count', 2)
            ->assertJsonPath('companies.0.revenue', 50000)
            ->assertJsonPath('companies.0.vehicles_count', 2)
            ->assertJsonPath('companies.0.default_branch_id', $alpha['branch']->id)
            ->assertJsonPath('companies.1.name', 'Beta Carriers')
            ->assertJsonPath('companies.1.profit', 15000)
            ->assertJsonPath('cash.receivables.outstanding', 71500)
            ->assertJsonPath('cash.receivables.due_today', 31500)
            ->assertJsonPath('cash.receivables.overdue', 40000)
            ->assertJsonPath('cash.top_outstanding_customers.0.name', 'Reliance Cargo')
            ->assertJsonMissingPath('cash.payables')
            ->assertJsonMissingPath('cash.top_payables')
            ->assertJsonPath('expenses.total', 10000)
            ->assertJsonPath('expenses.categories.0.key', 'fuel')
            ->assertJsonPath('expenses.categories.0.percent', 60)
            ->assertJsonMissingPath('branches');

        $attention = collect($response->json('attention'))->keyBy('key');

        $this->assertSame(1, $attention['idle_trucks']['count']);
        $this->assertSame(1, $attention['invoices_overdue']['count']);
        $this->assertSame(1, $attention['emis_due']['count']);
        $this->assertSame('Beta Carriers', $attention['renewal_due']['companies'][0]['company_name']);
        $this->assertSame($beta['branch']->id, $attention['renewal_due']['companies'][0]['default_branch_id']);
        $this->assertNotContains('Foreign Transport', collect($response->json('companies'))->pluck('name'));
    }

    public function test_company_filter_limits_dashboard_to_one_owned_company(): void
    {
        [$superAdmin, , $beta] = $this->createPortfolio();

        $this->actingAs($superAdmin)
            ->getJson('/app/dashboard/group?from_date=2026-09-01&to_date=2026-09-15&trend_months=6&company_id='.$beta['tenant']->id)
            ->assertOk()
            ->assertJsonPath('filters.company_id', (string) $beta['tenant']->id)
            ->assertJsonCount(2, 'filters.companies')
            ->assertJsonPath('executive.companies_count', 1)
            ->assertJsonPath('executive.revenue.current', 30000)
            ->assertJsonPath('fleet.overview.total_trucks', 2)
            ->assertJsonCount(6, 'trend')
            ->assertJsonCount(1, 'companies');
    }

    public function test_overdue_service_marks_truck_as_maintenance_and_raises_attention(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $company = $this->createOwnedCompany($superAdmin, 'Gamma Movers');

        VehicleMaintenance::query()->create([
            'tenant_id' => $company['tenant']->id,
            'branch_id' => $company['branch']->id,
            'vehicle_id' => $company['vehicle']->id,
            'service_date' => '2026-06-01',
            'odometer_km' => 500,
            'cost_amount' => 4000,
            'next_service_due_date' => '2026-09-01',
        ]);

        VehicleDocument::query()->create([
            'tenant_id' => $company['tenant']->id,
            'branch_id' => $company['branch']->id,
            'vehicle_id' => $company['vehicle']->id,
            'document_type' => 'INSURANCE',
            'expiry_date' => '2026-10-15',
        ]);

        $response = $this->actingAs($superAdmin)
            ->getJson('/app/dashboard/group?from_date=2026-09-01&to_date=2026-09-15')
            ->assertOk()
            ->assertJsonPath('fleet.overview.total_trucks', 1)
            ->assertJsonPath('fleet.overview.maintenance', 1)
            ->assertJsonPath('fleet.overview.available', 0);

        $attention = collect($response->json('attention'))->keyBy('key');

        $this->assertSame(1, $attention['services_overdue']['count']);
        $this->assertSame(1, $attention['documents_expiring']['count']);
    }

    public function test_company_filter_rejects_company_owned_by_someone_else(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $foreign = $this->createOwnedCompany($this->createSuperAdmin(), 'Foreign Transport');

        $this->actingAs($superAdmin)
            ->getJson('/app/dashboard/group?company_id='.$foreign['tenant']->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_dashboard_returns_empty_portfolio_for_super_admin_without_companies(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->getJson('/app/dashboard/group')
            ->assertOk()
            ->assertJsonPath('executive.companies_count', 0)
            ->assertJsonPath('executive.revenue.current', 0)
            ->assertJsonPath('executive.revenue.change_percent', null)
            ->assertJsonPath('fleet.overview.total_trucks', 0)
            ->assertJsonPath('companies', [])
            ->assertJsonPath('attention', [])
            ->assertJsonCount(12, 'trend');
    }

    public function test_dashboard_rejects_future_dates(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->getJson('/app/dashboard/group?from_date=2026-09-01&to_date=2026-09-20')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_date']);
    }

    public function test_non_super_admin_cannot_access_group_dashboard(): void
    {
        $company = $this->createOwnedCompany($this->createSuperAdmin(), 'Staff Company');

        $admin = User::query()->create([
            'tenant_id' => $company['tenant']->id,
            'branch_id' => $company['branch']->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $this->actingAs($admin)
            ->getJson('/app/dashboard/group')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function createPortfolio(): array
    {
        $superAdmin = $this->createSuperAdmin();

        $alpha = $this->createOwnedCompany($superAdmin, 'Alpha Logistics');
        $beta = $this->createOwnedCompany($superAdmin, 'Beta Carriers', ['ends_on' => '2026-09-25']);
        $foreign = $this->createOwnedCompany($this->createSuperAdmin(), 'Foreign Transport');

        $alphaIdleTruck = $this->createVehicle($alpha, 'MH12IDLE01');
        $alphaIdleTruck->forceFill(['created_at' => '2026-08-20 09:00:00'])->save();
        $this->createVehicle($beta, 'MH12OFF001', ['is_active' => false]);

        $alphaCustomer = $this->createCustomer($alpha, 'Reliance Cargo');
        $betaCustomer = $this->createCustomer($beta, 'Tata Freight');

        $alphaTrip = $this->createTrip($alpha, '2026-09-05 08:00:00', '2026-09-06 18:00:00', 30000, 10000, customer: $alphaCustomer);
        $this->createTrip($alpha, '2026-09-14 08:00:00', null, 20000, 5000, TripStatus::InProgress);
        $this->createTrip($alpha, '2026-08-05 08:00:00', '2026-08-06 18:00:00', 40000, 16000);
        $betaTrip = $this->createTrip($beta, '2026-09-12 08:00:00', '2026-09-13 18:00:00', 30000, 15000, customer: $betaCustomer);
        $this->createTrip($beta, '2026-09-11 08:00:00', null, 99999, 0, TripStatus::Cancelled);
        $this->createTrip($foreign, '2026-09-06 08:00:00', '2026-09-07 08:00:00', 70000, 5000);

        foreach ([TripExpenseType::Diesel->value => 6000, TripExpenseType::Toll->value => 4000] as $type => $amount) {
            TripExpense::query()->create([
                'tenant_id' => $alpha['tenant']->id,
                'branch_id' => $alpha['branch']->id,
                'trip_id' => $alphaTrip->id,
                'expense_type' => $type,
                'expense_date' => '2026-09-05',
                'amount' => $amount,
            ]);
        }

        $alphaInvoice = Invoice::factory()->create([
            'tenant_id' => $alpha['tenant']->id,
            'branch_id' => $alpha['branch']->id,
            'trip_id' => $alphaTrip->id,
            'customer_id' => $alphaCustomer->id,
            'total_amount' => 52500,
            'paid_amount' => 12500,
            'invoice_date' => '2026-09-06',
            'due_date' => '2026-09-10',
        ]);

        Invoice::factory()->create([
            'tenant_id' => $beta['tenant']->id,
            'branch_id' => $beta['branch']->id,
            'trip_id' => $betaTrip->id,
            'customer_id' => $betaCustomer->id,
            'total_amount' => 31500,
            'paid_amount' => 0,
            'invoice_date' => '2026-09-13',
            'due_date' => '2026-09-15',
        ]);

        InvoicePayment::factory()->create([
            'tenant_id' => $alpha['tenant']->id,
            'branch_id' => $alpha['branch']->id,
            'invoice_id' => $alphaInvoice->id,
            'amount' => 12500,
            'paid_at' => '2026-09-08',
        ]);

        Emi::query()->create([
            'tenant_id' => $alpha['tenant']->id,
            'branch_id' => $alpha['branch']->id,
            'vehicle_id' => $alpha['vehicle']->id,
            'lender_name' => 'HDFC Bank',
            'emi_amount' => 25000,
            'due_date' => '2026-09-15',
            'status' => 'DUE',
        ]);

        Emi::query()->create([
            'tenant_id' => $beta['tenant']->id,
            'branch_id' => $beta['branch']->id,
            'vehicle_id' => $beta['vehicle']->id,
            'lender_name' => 'ICICI Bank',
            'emi_amount' => 30000,
            'due_date' => '2026-09-30',
            'status' => 'DUE',
        ]);

        Challan::query()->create([
            'tenant_id' => $beta['tenant']->id,
            'branch_id' => $beta['branch']->id,
            'vehicle_id' => $beta['vehicle']->id,
            'challan_number' => 'CH-001',
            'challan_date' => '2026-07-10',
            'amount' => 1500,
            'status' => 'UNPAID',
        ]);

        return [$superAdmin, $alpha, $beta];
    }

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    /**
     * @param  array<string, mixed>  $subscriptionAttributes
     * @return array{tenant: Tenant, branch: Branch, vehicle: Vehicle, driver: Driver, station: Station}
     */
    private function createOwnedCompany(User $owner, string $name, array $subscriptionAttributes = []): array
    {
        $tenant = Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str()->slug($name).'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant, array_merge([
            'ends_on' => '2027-06-30',
        ], $subscriptionAttributes));

        $branch = $this->createBranchForTenant($tenant);
        $company = ['tenant' => $tenant, 'branch' => $branch];

        $vehicle = $this->createVehicle($company, 'MH12'.fake()->unique()->numerify('TR####'));

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('98########'),
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        $city = City::query()->whereHas('state', fn ($query) => $query->where('code', 'MH'))->where('name', 'Mumbai')->firstOrFail();

        $station = Station::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'name' => $name.' Yard',
        ]);

        return compact('tenant', 'branch', 'vehicle', 'driver', 'station');
    }

    /**
     * @param  array{tenant: Tenant, branch: Branch}  $company
     * @param  array<string, mixed>  $attributes
     */
    private function createVehicle(array $company, string $registrationNumber, array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'tenant_id' => $company['tenant']->id,
            'branch_id' => $company['branch']->id,
            'registration_number' => $registrationNumber,
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
            ...$attributes,
        ]);
    }

    /**
     * @param  array{tenant: Tenant}  $company
     */
    private function createCustomer(array $company, string $name): Customer
    {
        return Customer::query()->create([
            'tenant_id' => $company['tenant']->id,
            'name' => $name,
        ]);
    }

    /**
     * @param  array{tenant: Tenant, branch: Branch, vehicle: Vehicle, driver: Driver, station: Station}  $company
     */
    private function createTrip(
        array $company,
        string $startAt,
        ?string $endAt,
        float $freight,
        float $expense,
        TripStatus $status = TripStatus::Completed,
        ?Customer $customer = null,
    ): Trip {
        return Trip::query()->create([
            'tenant_id' => $company['tenant']->id,
            'branch_id' => $company['branch']->id,
            'vehicle_id' => $company['vehicle']->id,
            'driver_id' => $company['driver']->id,
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name,
            'trip_number' => 'TRIP-'.fake()->unique()->numerify('#####'),
            'start_station_id' => $company['station']->id,
            'trip_type' => 'ONE_WAY',
            'status' => $status->value,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'start_meter_reading_km' => 1000,
            'total_distance_km' => 100,
            'total_freight_amount' => $freight,
            'total_expense_amount' => $expense,
            'total_custom_amount' => 0,
        ]);
    }
}
