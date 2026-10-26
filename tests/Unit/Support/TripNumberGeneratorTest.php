<?php

namespace Tests\Unit\Support;

use App\Branch;
use App\BranchSetting;
use App\Invoice;
use App\Support\TripNumberGenerator;
use App\Tenant;
use App\Trip;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private TripNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TripNumberGenerator();
    }

    private function createTenantAndBranch(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Gen Co',
            'slug' => 'gen-co-' . uniqid(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Main',
            'branch_contact_number' => '9876543210',
            'head_office' => true,
            'inherit_company_gst' => true,
        ]);

        return [$tenant, $branch];
    }

    private function createVehicle(string $tenantId): \App\Vehicle
    {
        return \App\Vehicle::query()->create([
            'tenant_id' => $tenantId,
            'registration_number' => 'MH12AB' . rand(1000, 9999),
            'vehicle_type' => 'truck',
            'is_active' => true,
        ]);
    }

    private function createDriver(string $tenantId): \App\Driver
    {
        return \App\Driver::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Driver ' . rand(100, 999),
            'phone' => '98' . rand(10000000, 99999999),
            'is_active' => true,
        ]);
    }

    private function createCustomer(string $tenantId): \App\Customer
    {
        return \App\Customer::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Cust ' . rand(100, 999),
        ]);
    }

    public function test_preview_with_null_context_uses_fallback_settings(): void
    {
        $previewTrip = $this->generator->preview(
            TripNumberGenerator::DOCUMENT_TRIP,
            null,
            null,
            '2026-05-15',
            3
        );

        $this->assertSame(3, $previewTrip['sequence']);
        $this->assertStringStartsWith('TRIP-BR000', $previewTrip['number']);

        $previewInvoice = $this->generator->preview(
            TripNumberGenerator::DOCUMENT_INVOICE,
            null,
            null,
            '2026-05-15',
            1
        );

        $this->assertSame(1, $previewInvoice['sequence']);
        $this->assertStringStartsWith('INV-BR000', $previewInvoice['number']);
    }

    public function test_allocate_provisions_settings_and_increments_sequence(): void
    {
        [$tenant, $branch] = $this->createTenantAndBranch();

        $allocated1 = $this->generator->allocate(
            TripNumberGenerator::DOCUMENT_TRIP,
            $tenant->id,
            $branch->id,
            '2026-05-15'
        );

        $this->assertSame(1, $allocated1['sequence']);
        $this->assertNotEmpty($allocated1['number']);

        // Persist a trip with this number to verify next sequence increases
        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $this->createVehicle($tenant->id)->id,
            'driver_id' => $this->createDriver($tenant->id)->id,
            'trip_number' => $allocated1['number'],
            'start_at' => now(),
        ]);

        $allocated2 = $this->generator->allocate(
            TripNumberGenerator::DOCUMENT_TRIP,
            $tenant->id,
            $branch->id,
            '2026-05-15'
        );

        $this->assertSame(2, $allocated2['sequence']);
    }

    public function test_generate_requires_tenant_and_branch_context(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant context is required');
        $this->generator->generate(null, 1);
    }

    public function test_generate_requires_branch_context(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Branch context is required');
        $this->generator->generate('tenant-1', null);
    }

    public function test_generate_creates_formatted_trip_number(): void
    {
        [$tenant, $branch] = $this->createTenantAndBranch();

        $tripNumber = $this->generator->generate($tenant->id, $branch->id, Carbon::parse('2026-06-01'));
        $this->assertNotEmpty($tripNumber);
    }

    public function test_allocate_throws_validation_exception_on_duplicate_or_invalid_sequence(): void
    {
        [$tenant, $branch] = $this->createTenantAndBranch();

        $allocated = $this->generator->allocate(
            TripNumberGenerator::DOCUMENT_TRIP,
            $tenant->id,
            $branch->id,
            '2026-05-15',
            5
        );

        Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $this->createVehicle($tenant->id)->id,
            'driver_id' => $this->createDriver($tenant->id)->id,
            'trip_number' => $allocated['number'],
            'start_at' => now(),
        ]);

        // Attempting to allocate sequence 5 again throws ValidationException
        $this->expectException(ValidationException::class);
        $this->generator->allocate(
            TripNumberGenerator::DOCUMENT_TRIP,
            $tenant->id,
            $branch->id,
            '2026-05-15',
            5
        );
    }

    public function test_format_configurations_and_example(): void
    {
        [$tenant, $branch] = $this->createTenantAndBranch();
        $settings = BranchSetting::query()->where('branch_id', $branch->id)->first()
            ?? BranchSetting::createForBranch($branch->id);

        // 1. Year type: Financial Year with 2 digits
        $settings->update([
            'trip_year_type' => BranchSetting::YEAR_TYPE_FINANCIAL_YEAR,
            'trip_year_digits' => 2,
            'trip_separator_1' => '/',
            'trip_separator_2' => '/',
            'trip_sequence_digits' => 4,
        ]);
        $date1 = Carbon::parse('2026-08-10'); // FY 26-27
        $example1 = $this->generator->formatExample($settings, TripNumberGenerator::DOCUMENT_TRIP, $date1, 42);
        $this->assertStringContainsString('26-27/0042', $example1);

        // 2. Year type: Blank
        $settings->update([
            'trip_year_type' => BranchSetting::YEAR_TYPE_BLANK,
        ]);
        $example2 = $this->generator->formatExample($settings, TripNumberGenerator::DOCUMENT_TRIP, $date1, 7);
        $this->assertStringEndsWith('/0007', $example2);

        // 3. Year type: Calendar year with 2 digits
        $settings->update([
            'trip_year_type' => 'calendar',
            'trip_year_digits' => 2,
        ]);
        $example3 = $this->generator->formatExample($settings, TripNumberGenerator::DOCUMENT_TRIP, $date1, 1);
        $this->assertStringContainsString('/26/', $example3);

        // 4. Invoice document type example
        $invoiceExample = $this->generator->formatExample($settings, TripNumberGenerator::DOCUMENT_INVOICE, $date1, 99);
        $this->assertNotEmpty($invoiceExample);
    }

    public function test_invoice_soft_deletes_free_number_and_trashed_trips_keep_reserved(): void
    {
        [$tenant, $branch] = $this->createTenantAndBranch();

        // Trip: Trashed trips still keep number reserved
        $allocatedTrip = $this->generator->allocate(
            TripNumberGenerator::DOCUMENT_TRIP,
            $tenant->id,
            $branch->id,
            '2026-05-15'
        );
        $trip1 = Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $this->createVehicle($tenant->id)->id,
            'driver_id' => $this->createDriver($tenant->id)->id,
            'trip_number' => $allocatedTrip['number'],
            'start_at' => now(),
        ]);
        $trip1->delete(); // Soft-deleted

        $previewTrip = $this->generator->preview(
            TripNumberGenerator::DOCUMENT_TRIP,
            $tenant->id,
            $branch->id,
            '2026-05-15'
        );
        // Candidate skips used 1 and picks 2
        $this->assertSame(2, $previewTrip['sequence']);

        // Invoices: Soft-deleted invoices do not reserve number
        $allocatedInv = $this->generator->allocate(
            TripNumberGenerator::DOCUMENT_INVOICE,
            $tenant->id,
            $branch->id,
            '2026-05-15'
        );
        $inv1 = Invoice::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'customer_id' => $this->createCustomer($tenant->id)->id,
            'document_type' => \App\Enums\InvoiceDocumentType::TaxInvoice,
            'invoice_number' => $allocatedInv['number'],
            'invoice_date' => now(),
        ]);
        $inv1->delete(); // Soft-deleted

        $previewInv = $this->generator->preview(
            TripNumberGenerator::DOCUMENT_INVOICE,
            $tenant->id,
            $branch->id,
            '2026-05-15'
        );
        $this->assertSame(1, $previewInv['sequence']);
    }
}
