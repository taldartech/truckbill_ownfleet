<?php

namespace Tests\Unit\Models;

use App\AppNotification;
use App\Attendance;
use App\AttendanceMonthLock;
use App\Battery;
use App\BatteryInspection;
use App\BatteryInstallation;
use App\Branch;
use App\BranchSetting;
use App\Broker;
use App\Country;
use App\Customer;
use App\DocumentAlertSetting;
use App\DocumentType;
use App\Driver;
use App\DriverAdvanceEntry;
use App\DriverAssignment;
use App\Enums\AttendanceMonthStatus;
use App\Enums\BatteryStatus;
use App\Enums\FreightBillingStatus;
use App\Enums\InvoicePaymentStatus;
use App\Enums\PickupDeliveryStatus;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Enums\WagePaymentStatus;
use App\Invoice;
use App\InvoicePayment;
use App\MaintenanceServiceType;
use App\Models\TenantSubscription;
use App\OnboardingProvisioningLog;
use App\Services\SubscriptionAccessService;
use App\Station;
use App\Support\BranchContext;
use App\Support\MaintenanceDueStatus;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\TripExpenseTypeMaster;
use App\TripPickup;
use App\TripRouteLeg;
use App\TripStatusHistory;
use App\Tyre;
use App\TyreAuditLog;
use App\TyreInspection;
use App\TyreLayout;
use App\User;
use App\UserRole;
use App\Vehicle;
use App\VehicleDocument;
use App\VehicleMaintenance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

    public function test_app_notification_relations(): void
    {
        $notification = new AppNotification();
        $this->assertInstanceOf(BelongsTo::class, $notification->user());
    }

    public function test_attendance_wage_paid_status(): void
    {
        $paid = new Attendance(['wage_payment_status' => WagePaymentStatus::Paid]);
        $this->assertTrue($paid->isWagePaid());

        $pending = new Attendance(['wage_payment_status' => WagePaymentStatus::Unpaid]);
        $this->assertFalse($pending->isWagePaid());
    }

    public function test_attendance_month_lock_is_open(): void
    {
        $open = new AttendanceMonthLock(['status' => AttendanceMonthStatus::Open]);
        $this->assertTrue($open->isEditable());

        $closed = new AttendanceMonthLock(['status' => AttendanceMonthStatus::Closed]);
        $this->assertFalse($closed->isEditable());
    }

    public function test_battery_relations_and_scopes(): void
    {
        $battery = new Battery();
        $this->assertInstanceOf(HasMany::class, $battery->replacementsAsOld());
        $this->assertInstanceOf(HasMany::class, $battery->replacementsAsNew());

        $inStockSql = Battery::query()->inStock()->toSql();
        $this->assertStringContainsString('status', $inStockSql);
        $this->assertContains(BatteryStatus::InStock->value, Battery::query()->inStock()->getBindings());

        $installedSql = Battery::query()->installed()->toSql();
        $this->assertStringContainsString('status', $installedSql);
        $this->assertContains(BatteryStatus::Installed->value, Battery::query()->installed()->getBindings());

        $underInspectionSql = Battery::query()->underInspection()->toSql();
        $this->assertStringContainsString('status', $underInspectionSql);
        $this->assertContains(BatteryStatus::UnderInspection->value, Battery::query()->underInspection()->getBindings());
    }

    public function test_battery_inspection_relations(): void
    {
        $inspection = new BatteryInspection();
        $this->assertInstanceOf(BelongsTo::class, $inspection->battery());
    }

    public function test_branch_relations_and_logo_filesystem_path(): void
    {
        $branch = new Branch();
        $this->assertInstanceOf(HasMany::class, $branch->users());

        Storage::fake('public');

        // File exists
        Storage::disk('public')->put('logos/test-branch.png', 'fake-image-content');
        $branchWithLogo = new Branch([
            'branch_logo' => 'logos/test-branch.png',
            'use_company_logo' => false,
        ]);
        $this->assertNotNull($branchWithLogo->logoFilesystemPathForPdf());

        // File does not exist
        $branchMissingLogo = new Branch([
            'branch_logo' => 'logos/missing.png',
            'use_company_logo' => false,
        ]);
        $this->assertNull($branchMissingLogo->logoFilesystemPathForPdf());

        // No logo set
        $branchNoLogo = new Branch(['use_company_logo' => false]);
        $this->assertNull($branchNoLogo->logoFilesystemPathForPdf());
    }

    public function test_branch_setting_relations(): void
    {
        $setting = new BranchSetting();
        $this->assertInstanceOf(BelongsTo::class, $setting->branch());
    }

    public function test_country_relations(): void
    {
        $country = new Country();
        $this->assertInstanceOf(HasMany::class, $country->states());
    }

    public function test_document_alert_setting_relations(): void
    {
        $setting = new DocumentAlertSetting();
        $this->assertInstanceOf(BelongsTo::class, $setting->tenant());
    }

    public function test_document_type_relations(): void
    {
        $type = new DocumentType();
        $this->assertInstanceOf(BelongsTo::class, $type->createdBy());
        $this->assertInstanceOf(BelongsTo::class, $type->updatedBy());
    }

    public function test_driver_and_advance_entry_relations(): void
    {
        $driver = new Driver();
        $this->assertInstanceOf(BelongsTo::class, $driver->tenant());
        $this->assertInstanceOf(HasMany::class, $driver->primaryAttendances());
        $this->assertInstanceOf(HasMany::class, $driver->advanceEntries());

        $entry = new DriverAdvanceEntry();
        $this->assertInstanceOf(BelongsTo::class, $entry->driver());
        $this->assertInstanceOf(BelongsTo::class, $entry->creator());
    }

    public function test_driver_assignment_status_on(): void
    {
        $assignment = new DriverAssignment([
            'assigned_from' => '2026-06-01',
            'assigned_to' => '2026-06-15',
        ]);

        $this->assertSame('scheduled', $assignment->statusOn('2026-05-31'));
        $this->assertSame('active', $assignment->statusOn('2026-06-05'));
        $this->assertSame('ended', $assignment->statusOn('2026-06-15'));
        $this->assertSame('ended', $assignment->statusOn('2026-06-20'));

        $ongoingAssignment = new DriverAssignment([
            'assigned_from' => '2026-06-01',
            'assigned_to' => null,
        ]);
        $this->assertSame('active', $ongoingAssignment->statusOn('2026-06-10'));
    }

    public function test_invoice_linked_trips_and_labels_and_status(): void
    {
        // Unloaded relations
        $invoice = new Invoice();
        $this->assertSame([], $invoice->linkedTripsPayload());
        $this->assertSame('—', $invoice->tripsLabel());

        // Single trip with enum status
        $tripSingle = new Trip(['trip_number' => 'TRIP-SINGLE', 'status' => TripStatus::Completed]);
        $tripSingle->id = 'trip-1';
        $invoice->setRelation('trip', $tripSingle);
        $payloadSingle = $invoice->linkedTripsPayload();
        $this->assertCount(1, $payloadSingle);
        $this->assertSame('COMPLETED', $payloadSingle[0]['status']);
        $this->assertSame('TRIP-SINGLE', $invoice->tripsLabel());

        // Single trip with null status
        $tripSingleNull = new Trip(['trip_number' => 'TRIP-NULL', 'status' => null]);
        $tripSingleNull->id = 'trip-null';
        $invoiceNull = new Invoice();
        $invoiceNull->setRelation('trip', $tripSingleNull);
        $payloadNull = $invoiceNull->linkedTripsPayload();
        $this->assertNull($payloadNull[0]['status']);

        // Multiple trips with enum status
        $tripA = new Trip(['trip_number' => 'TRIP-A', 'status' => TripStatus::Completed]);
        $tripA->id = 'trip-a';
        $tripB = new Trip(['trip_number' => 'TRIP-B', 'status' => TripStatus::Invoiced]);
        $tripB->id = 'trip-b';
        $tripC = new Trip(['trip_number' => 'TRIP-C', 'status' => null]);
        $tripC->id = 'trip-c';

        $invoiceMulti = new Invoice();
        $invoiceMulti->setRelation('trips', new Collection([$tripA, $tripB, $tripC]));
        $payloadMulti = $invoiceMulti->linkedTripsPayload();
        $this->assertCount(3, $payloadMulti);
        $this->assertSame('TRIP-A + 2 more', $invoiceMulti->tripsLabel());

        // Multiple trips without numbers
        $tripNoNum1 = new Trip(['trip_number' => null, 'status' => TripStatus::Completed]);
        $tripNoNum1->id = 't1';
        $tripNoNum2 = new Trip(['trip_number' => '', 'status' => TripStatus::Completed]);
        $tripNoNum2->id = 't2';
        $invoiceNoNums = new Invoice();
        $invoiceNoNums->setRelation('trips', new Collection([$tripNoNum1, $tripNoNum2]));
        $this->assertSame('2 trips', $invoiceNoNums->tripsLabel());

        // isLatestForBranch with missing branch_id
        $this->assertFalse($invoice->isLatestForBranch());

        // isOverdue when paid
        $invoicePaid = new Invoice([
            'due_date' => now()->subDays(5),
            'payment_status' => InvoicePaymentStatus::Paid,
        ]);
        $this->assertFalse($invoicePaid->isOverdue());

        // refreshPaymentStatus when cancelled returns early
        $cancelledInvoice = new Invoice(['payment_status' => InvoicePaymentStatus::Cancelled]);
        $cancelledInvoice->refreshPaymentStatus();
        $this->assertTrue($cancelledInvoice->isCancelled());
    }

    public function test_invoice_payment_accessor_and_relation(): void
    {
        $payment = new InvoicePayment([
            'amount' => 1000.50,
            'tds_amount' => 50.25,
            'deduction_amount' => 25.00,
        ]);

        $this->assertSame(1075.75, $payment->total_settled_amount);
        $this->assertInstanceOf(BelongsTo::class, $payment->invoice());
    }

    public function test_maintenance_service_type_relations(): void
    {
        $mst = new MaintenanceServiceType();
        $this->assertInstanceOf(BelongsTo::class, $mst->createdBy());
        $this->assertInstanceOf(BelongsTo::class, $mst->updatedBy());
        $this->assertInstanceOf(HasMany::class, $mst->vehicleMaintenances());
    }

    public function test_onboarding_provisioning_log(): void
    {
        $log = new OnboardingProvisioningLog([
            'request_payload' => ['k1' => 'v1'],
            'response_payload' => ['k2' => 'v2'],
        ]);

        $this->assertIsArray($log->request_payload);
        $this->assertIsArray($log->response_payload);
        $this->assertInstanceOf(BelongsTo::class, $log->user());
    }

    public function test_tenant_methods_and_relations(): void
    {
        $tenant = new Tenant([
            'name' => 'Acme Logistics',
            'slug' => 'acme-logistics',
            'document_color' => '#FFFFFF',
        ]);

        // Relations
        $this->assertInstanceOf(BelongsTo::class, $tenant->owner());
        $this->assertInstanceOf(HasMany::class, $tenant->users());
        $this->assertInstanceOf(HasMany::class, $tenant->branches());
        $this->assertInstanceOf(HasMany::class, $tenant->pendingUpcomingRenewals());

        // Colors
        $this->assertSame('#FFFFFF', $tenant->documentColor());
        $this->assertSame('#000000', $tenant->documentTextColor());
        $this->assertSame('#FFFFFF', $tenant->documentColorWithOpacity(0.5));

        $darkTenant = new Tenant(['document_color' => '#000000']);
        $this->assertSame('#FFFFFF', $darkTenant->documentTextColor());
        $this->assertSame('#808080', $darkTenant->documentColorWithOpacity(0.5));

        $invalidTenant = new Tenant(['document_color' => 'invalid-color']);
        $this->assertNull($invalidTenant->documentColor());
        $this->assertSame('#000000', $invalidTenant->documentTextColor());
        $this->assertNull($invalidTenant->documentColorWithOpacity(0.5));

        // Subscriptions
        $this->assertFalse($tenant->isPlanActive());
        $activeSub = new TenantSubscription(['status' => 'active', 'ends_on' => now()->addDays(30)]);
        $tenant->setRelation('subscription', $activeSub);
        $this->assertTrue($tenant->isPlanActive());

        $mockAccess = $this->createMock(SubscriptionAccessService::class);
        $mockAccess->method('accessPayloadForTenant')->willReturn([
            'can_write' => true,
            'can_export' => true,
            'can_print' => true,
        ]);
        $this->app->instance(SubscriptionAccessService::class, $mockAccess);
        $this->assertTrue($tenant->subscriptionCanExport());
        $this->assertTrue($tenant->subscriptionCanPrint());

        // Logo filesystem path missing
        $this->assertNull($tenant->logoFilesystemPathForPdf());

        // DB uniqueness collisions
        $created1 = Tenant::query()->create([
            'name' => 'Alpha Express',
            'short_name' => 'ALPHA',
            'slug' => 'alpha-express',
            'status' => 'active',
        ]);

        $uniqueShort = Tenant::ensureUniqueShortName('Alpha');
        $this->assertSame('ALPHA1', $uniqueShort);

        $uniqueSlug = Tenant::ensureUniqueSlug('Alpha Express');
        $this->assertSame('alpha-express-1', $uniqueSlug);

        // Upcoming renewal check
        $this->assertFalse($created1->hasPendingUpcomingRenewal());
    }

    public function test_trip_relations_and_methods(): void
    {
        $trip = new Trip();
        $this->assertInstanceOf(BelongsTo::class, $trip->vehicle());
        $this->assertInstanceOf(BelongsTo::class, $trip->driver());
        $this->assertInstanceOf(BelongsTo::class, $trip->substituteDriver());
        $this->assertInstanceOf(BelongsTo::class, $trip->customer());
        $this->assertInstanceOf(BelongsTo::class, $trip->broker());
        $this->assertInstanceOf(BelongsTo::class, $trip->startStation());
        $this->assertInstanceOf(HasMany::class, $trip->routeLegs());
        $this->assertInstanceOf(HasMany::class, $trip->pickups());
        $this->assertInstanceOf(HasMany::class, $trip->invoices());
        $this->assertInstanceOf(BelongsToMany::class, $trip->linkedInvoices());
        $this->assertInstanceOf(HasMany::class, $trip->expenses());
        $this->assertInstanceOf(HasMany::class, $trip->statusHistories());
        $this->assertInstanceOf(BelongsTo::class, $trip->archivedBy());
        $this->assertInstanceOf(HasMany::class, $trip->auditLogs());

        // Casts verification
        $trip->setRawAttributes([
            'trip_type' => 'MULTI_POINT',
            'status' => 'COMPLETED',
            'start_meter_reading_km' => '1000.50',
            'total_freight_amount' => '15000.75',
            'total_custom_amount' => '1200.25',
            'total_expense_amount' => '8000.50',
        ]);
        $this->assertInstanceOf(TripType::class, $trip->trip_type);
        $this->assertInstanceOf(TripStatus::class, $trip->status);

        // isMultiPoint evaluations
        $this->assertTrue($trip->isMultiPoint());

        $trip->trip_type = TripType::MultiPoint;
        $this->assertTrue($trip->isMultiPoint());

        $trip->trip_type = TripType::OneWay;
        $this->assertFalse($trip->isMultiPoint());

        $trip->setRawAttributes(['trip_type' => 'ROUND_TRIP']);
        $this->assertFalse($trip->isMultiPoint());

        $trip->setRawAttributes(['trip_type' => null]);
        $this->assertFalse($trip->isMultiPoint());

        // profitAmount dynamic business logic: (freight + custom) - expense
        $freight = 15000.75;
        $custom = 1200.25;
        $expense = 8000.50;
        $trip->total_freight_amount = $freight;
        $trip->total_custom_amount = $custom;
        $trip->total_expense_amount = $expense;
        $this->assertEqualsWithDelta($freight + $custom - $expense, $trip->profitAmount(), 0.001);

        // Net loss condition
        $heavyExpense = 25000.00;
        $trip->total_expense_amount = $heavyExpense;
        $this->assertEqualsWithDelta($freight + $custom - $heavyExpense, $trip->profitAmount(), 0.001);
        $this->assertLessThan(0.0, $trip->profitAmount());

        $freshTrip = new Trip();
        $this->assertSame(0.0, $freshTrip->profitAmount());

        // hasInvoices with invoices_exists attribute shortcut
        $tripAttrTrue = new Trip();
        $tripAttrTrue->invoices_exists = true;
        $this->assertTrue($tripAttrTrue->hasInvoices());

        $tripAttrFalse = new Trip();
        $tripAttrFalse->invoices_exists = false;
        $this->assertFalse($tripAttrFalse->hasInvoices());

        // hasInvoices with relationLoaded invoices
        $activeInvoice = new Invoice(['payment_status' => InvoicePaymentStatus::Pending]);
        $cancelledInvoice = new Invoice(['payment_status' => InvoicePaymentStatus::Cancelled]);

        $trip->setRelation('invoices', new Collection([$activeInvoice]));
        $this->assertTrue($trip->hasInvoices());

        // hasInvoices fallback to linkedInvoices when direct invoices are all cancelled
        $tripLinkedOnly = new Trip();
        $tripLinkedOnly->setRelation('invoices', new Collection([$cancelledInvoice]));
        $tripLinkedOnly->setRelation('linkedInvoices', new Collection([$activeInvoice]));
        $this->assertTrue($tripLinkedOnly->hasInvoices());

        // hasInvoices when both direct and linked invoices are cancelled
        $tripBothCancelled = new Trip();
        $tripBothCancelled->setRelation('invoices', new Collection([$cancelledInvoice]));
        $tripBothCancelled->setRelation('linkedInvoices', new Collection([$cancelledInvoice]));
        $this->assertFalse($tripBothCancelled->hasInvoices());

        // canGenerateInvoices: Trashed trip cannot generate invoices
        $trashedTrip = new Trip(['status' => TripStatus::Completed]);
        $trashedTrip->deleted_at = now();
        $this->assertFalse($trashedTrip->canGenerateInvoices());

        // canGenerateInvoices: Ineligible statuses
        $this->assertFalse((new Trip(['status' => TripStatus::Pending]))->canGenerateInvoices());
        $this->assertFalse((new Trip(['status' => TripStatus::InProgress]))->canGenerateInvoices());
        $this->assertFalse((new Trip(['status' => TripStatus::Cancelled]))->canGenerateInvoices());
        $this->assertFalse((new Trip(['status' => null]))->canGenerateInvoices());

        // canGenerateInvoices: Completed trip with empty route legs
        $completedTrip = new Trip(['status' => TripStatus::Completed]);
        $completedTrip->setRelation('routeLegs', new Collection());
        $this->assertFalse($completedTrip->canGenerateInvoices());

        // canGenerateInvoices: Completed trip with non-revenue leg (weight <= 0)
        $emptyLeg = new TripRouteLeg(['customer_id' => 'cust-1', 'freight_amount' => 500, 'weight_ton' => 0]);
        $completedTrip->setRelation('routeLegs', new Collection([$emptyLeg]));
        $this->assertFalse($completedTrip->canGenerateInvoices());

        // canGenerateInvoices: Completed trip with missing customer_id
        $noCustLeg = new TripRouteLeg(['customer_id' => null, 'freight_amount' => 500, 'weight_ton' => 10]);
        $completedTrip->setRelation('routeLegs', new Collection([$noCustLeg]));
        $this->assertFalse($completedTrip->canGenerateInvoices());

        // canGenerateInvoices: Completed trip with zero/negative freight amount
        $zeroFreightLeg = new TripRouteLeg(['customer_id' => 'cust-1', 'freight_amount' => 0, 'weight_ton' => 10]);
        $completedTrip->setRelation('routeLegs', new Collection([$zeroFreightLeg]));
        $this->assertFalse($completedTrip->canGenerateInvoices());

        // canGenerateInvoices: Completed trip with invoiced billing status
        $invoicedBillingLeg = new TripRouteLeg([
            'customer_id' => 'cust-1',
            'freight_amount' => 500,
            'weight_ton' => 10,
            'billing_status' => FreightBillingStatus::Invoiced,
        ]);
        $completedTrip->setRelation('routeLegs', new Collection([$invoicedBillingLeg]));
        $this->assertFalse($completedTrip->canGenerateInvoices());

        // canGenerateInvoices: Valid unbilled leg on Completed trip
        $validUnbilledLeg = new TripRouteLeg([
            'customer_id' => 'cust-1',
            'freight_amount' => 500,
            'weight_ton' => 12.5,
            'billing_status' => 'unbilled',
        ]);
        $completedTrip->setRelation('routeLegs', new Collection([$validUnbilledLeg]));
        $this->assertTrue($completedTrip->canGenerateInvoices());

        // canGenerateInvoices: Valid billed leg on Invoiced trip
        $invoicedTrip = new Trip(['status' => TripStatus::Invoiced]);
        $validBilledLeg = new TripRouteLeg([
            'customer_id' => 'cust-1',
            'freight_amount' => 1500,
            'weight_ton' => 10,
            'billing_status' => FreightBillingStatus::Billed,
        ]);
        $invoicedTrip->setRelation('routeLegs', new Collection([$validBilledLeg]));
        $this->assertTrue($invoicedTrip->canGenerateInvoices());

        // canGenerateInvoices: Raw string billing_status fallback (when not instanceof FreightBillingStatus)
        $rawStringLeg = new TripRouteLeg([
            'customer_id' => 'cust-1',
            'freight_amount' => 500,
            'weight_ton' => 12.5,
        ]);
        $rawStringLeg->mergeCasts(['billing_status' => 'string']);
        $rawStringLeg->billing_status = 'unbilled';
        $completedTrip->setRelation('routeLegs', new Collection([$rawStringLeg]));
        $this->assertTrue($completedTrip->canGenerateInvoices());
    }

    public function test_trip_expense_and_type_master(): void
    {
        $expense = new TripExpense();
        $expense->setExpenseTypeAttribute(null);
        $this->assertNull($expense->expense_type);

        $master = new TripExpenseTypeMaster();
        $this->assertInstanceOf(BelongsTo::class, $master->superAdmin());
    }

    public function test_trip_pickup_and_route_leg(): void
    {
        $pickup = new TripPickup(['weight_ton' => 10.0]);
        $this->assertInstanceOf(BelongsTo::class, $pickup->trip());
        $this->assertInstanceOf(BelongsTo::class, $pickup->station());
        $this->assertInstanceOf(HasMany::class, $pickup->drops());

        // drops loaded with 0 delivered -> Pending status
        $pickup->setRelation('drops', new Collection());
        $this->assertSame(0.0, $pickup->deliveredWeightTon());
        $this->assertSame(10.0, $pickup->remainingWeightTon());
        $this->assertSame(PickupDeliveryStatus::Pending, $pickup->deliveryStatus());

        // Partial delivery: dynamic sum of multiple drops
        $drop1Weight = 3.25;
        $drop2Weight = 2.75;
        $drop1 = new TripRouteLeg(['weight_ton' => $drop1Weight]);
        $drop2 = new TripRouteLeg(['weight_ton' => $drop2Weight]);
        $pickup->setRelation('drops', new Collection([$drop1, $drop2]));
        $delivered = $drop1Weight + $drop2Weight;
        $this->assertSame($delivered, $pickup->deliveredWeightTon());
        $this->assertSame(10.0 - $delivered, $pickup->remainingWeightTon());
        $this->assertSame(PickupDeliveryStatus::Partial, $pickup->deliveryStatus());

        // Full delivery: remaining weight must reach exactly 0.0
        $dropFull = new TripRouteLeg(['weight_ton' => 10.0]);
        $pickup->setRelation('drops', new Collection([$dropFull]));
        $this->assertSame(10.0, $pickup->deliveredWeightTon());
        $this->assertSame(0.0, $pickup->remainingWeightTon());
        $this->assertSame(PickupDeliveryStatus::Delivered, $pickup->deliveryStatus());

        // Delivery tolerance margin edge case: delivered + 0.0005 >= pickupWeight
        $dropBoundary = new TripRouteLeg(['weight_ton' => 9.9996]);
        $pickup->setRelation('drops', new Collection([$dropBoundary]));
        $this->assertSame(PickupDeliveryStatus::Delivered, $pickup->deliveryStatus());

        // Over-delivery edge case: remaining weight must never be negative
        $dropExcess = new TripRouteLeg(['weight_ton' => 12.5]);
        $pickup->setRelation('drops', new Collection([$dropExcess]));
        $this->assertSame(0.0, $pickup->remainingWeightTon());
        $this->assertSame(PickupDeliveryStatus::Delivered, $pickup->deliveryStatus());

        // route leg empty/loaded
        $legEmpty = new TripRouteLeg(['weight_ton' => 0]);
        $this->assertTrue($legEmpty->isEmpty());

        $legLoaded = new TripRouteLeg(['weight_ton' => 15.5]);
        $this->assertFalse($legLoaded->isEmpty());
    }

    public function test_trip_and_pickup_database_integration_queries(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Trip DB Integration Tenant',
            'slug' => 'trip-db-integration-tenant',
            'status' => 'active',
        ]);
        $operational = $this->operationalAttributesForTenant($tenant);
        TenantContext::setTenantId($tenant->id);
        BranchContext::setBranchId($operational['branch_id']);

        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'DL01TR1234',
            'vehicle_type' => 'TRUCK',
        ]);

        $driver = Driver::query()->create([
            ...$operational,
            'name' => 'Integration Driver',
            'phone' => '9988776655',
            'daily_wage' => 850,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Integration Customer',
        ]);

        $trip = Trip::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'trip_number' => 'TRP-INT-001',
            'status' => TripStatus::Completed->value,
            'total_freight_amount' => 12000.0,
            'total_expense_amount' => 4000.0,
            'total_custom_amount' => 500.0,
        ]);

        // 1. Integration query: hasInvoices without relation loaded (returns false when no invoices exist)
        $this->assertFalse($trip->hasInvoices());

        // 2. Integration query: canGenerateInvoices without routeLegs relation loaded (queries route_legs from DB)
        $leg = TripRouteLeg::query()->create([
            ...$operational,
            'trip_id' => $trip->id,
            'customer_id' => $customer->id,
            'sequence_no' => 1,
            'distance_km' => 150,
            'weight_ton' => 14.5,
            'freight_amount' => 12000,
            'billing_status' => FreightBillingStatus::Unbilled->value,
        ]);

        $this->assertTrue($trip->canGenerateInvoices());

        // 3. Integration query: hasInvoices with direct active invoice in DB
        $invoice = Invoice::query()->create([
            ...$operational,
            'trip_id' => $trip->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-INT-001',
            'document_type' => 'tax_invoice',
            'invoice_date' => now()->toDateString(),
            'payment_status' => InvoicePaymentStatus::Pending->value,
            'total_amount' => 12000,
        ]);

        $this->assertTrue($trip->hasInvoices());

        // Direct invoice cancelled -> hasInvoices returns false
        $invoice->update(['payment_status' => InvoicePaymentStatus::Cancelled->value]);
        $this->assertFalse($trip->hasInvoices());

        // 4. Integration query: hasInvoices with linked invoice in invoice_trips table
        $linkedInvoice = Invoice::query()->create([
            ...$operational,
            'trip_id' => $trip->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-INT-002',
            'document_type' => 'tax_invoice',
            'invoice_date' => now()->toDateString(),
            'payment_status' => InvoicePaymentStatus::Pending->value,
            'total_amount' => 15000,
        ]);
        $trip->linkedInvoices()->attach($linkedInvoice->id);
        $this->assertTrue($trip->hasInvoices());

        // 5. Integration query: TripPickup deliveredWeightTon when drops relation is NOT loaded (queries DB sum)
        $station = Station::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Integration Station',
        ]);

        $pickup = TripPickup::query()->create([
            ...$operational,
            'trip_id' => $trip->id,
            'station_id' => $station->id,
            'pickup_code' => 'PCK-INT-01',
            'sequence_no' => 1,
            'weight_ton' => 20.0,
        ]);

        $this->assertSame(0.0, $pickup->deliveredWeightTon());
        $this->assertSame(20.0, $pickup->remainingWeightTon());
        $this->assertSame(PickupDeliveryStatus::Pending, $pickup->deliveryStatus());

        // Associate drops to pickup in DB
        $leg->update(['trip_pickup_id' => $pickup->id]);
        $freshPickup = TripPickup::query()->findOrFail($pickup->id);
        $this->assertFalse($freshPickup->relationLoaded('drops'));
        $this->assertSame(14.5, $freshPickup->deliveredWeightTon());
        $this->assertSame(5.5, $freshPickup->remainingWeightTon());
        $this->assertSame(PickupDeliveryStatus::Partial, $freshPickup->deliveryStatus());
    }

    public function test_trip_status_history_and_tyre_audit_and_inspection_and_layout(): void
    {
        $history = new TripStatusHistory();
        $this->assertInstanceOf(BelongsTo::class, $history->trip());
        $this->assertInstanceOf(BelongsTo::class, $history->changedBy());

        $audit = new TyreAuditLog();
        $this->assertInstanceOf(BelongsTo::class, $audit->performer());

        $inspection = new TyreInspection();
        $this->assertInstanceOf(BelongsTo::class, $inspection->tyre());
        $this->assertInstanceOf(BelongsTo::class, $inspection->createdBy());

        $layout = new TyreLayout();
        $this->assertInstanceOf(BelongsTo::class, $layout->tenant());
    }
}
