<?php

namespace Tests\Unit\Enums;

use App\Enums\BatteryCondition;
use App\Enums\BatteryRemovalReason;
use App\Enums\BatteryStatus;
use App\Enums\BatteryType;
use App\Enums\DropDeliveryStatus;
use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceParticularsMode;
use App\Enums\PickupDeliveryStatus;
use App\Enums\TaxLiability;
use App\Enums\TripExpenseType;
use App\Enums\TyrePosition;
use App\Enums\VehicleDocumentType;
use Tests\TestCase;

class ApplicationEnumsTest extends TestCase
{
    public function test_battery_condition_labels(): void
    {
        $this->assertSame('Good', BatteryCondition::Good->label());
        $this->assertSame('Fair / Monitor', BatteryCondition::Fair->label());
        $this->assertSame('Weak / Needs Attention', BatteryCondition::Weak->label());
        $this->assertSame('Failed / Replace', BatteryCondition::Failed->label());
    }

    public function test_battery_type_labels(): void
    {
        $this->assertSame('Lead Acid (Flooded)', BatteryType::LeadAcid->label());
        $this->assertSame('Maintenance Free (SMF)', BatteryType::MaintenanceFree->label());
        $this->assertSame('Tubular', BatteryType::Tubular->label());
        $this->assertSame('Lithium-Ion', BatteryType::LithiumIon->label());
        $this->assertSame('Gel Battery', BatteryType::Gel->label());
    }

    public function test_drop_delivery_status_labels(): void
    {
        $this->assertSame('Pending', DropDeliveryStatus::Pending->label());
        $this->assertSame('Delivered', DropDeliveryStatus::Delivered->label());
    }

    public function test_gst_registration_type_labels_and_predicates(): void
    {
        $this->assertTrue(GstRegistrationType::Regular->showsGstFields());
        $this->assertTrue(GstRegistrationType::Composition->showsGstFields());
        $this->assertFalse(GstRegistrationType::Unregistered->showsGstFields());

        $this->assertSame('Regular', GstRegistrationType::Regular->label());
        $this->assertSame('Composition', GstRegistrationType::Composition->label());
        $this->assertSame('Unregistered', GstRegistrationType::Unregistered->label());
    }

    public function test_invoice_particulars_mode_labels(): void
    {
        $this->assertSame('Separate line per route', InvoiceParticularsMode::Separate->label());
        $this->assertSame('Combined (single line)', InvoiceParticularsMode::Combined->label());
    }

    public function test_tax_liability_labels_and_legacy_mapper(): void
    {
        $this->assertSame('Forward Charge', TaxLiability::ForwardCharge->label());
        $this->assertSame('Reverse Charge (RCM)', TaxLiability::ReverseCharge->label());

        $this->assertNull(TaxLiability::fromLegacy(null));
        $this->assertNull(TaxLiability::fromLegacy(''));
        $this->assertNull(TaxLiability::fromLegacy('invalid_string'));

        $this->assertSame(TaxLiability::ForwardCharge, TaxLiability::fromLegacy('forward charge'));
        $this->assertSame(TaxLiability::ForwardCharge, TaxLiability::fromLegacy('forward_charge'));

        $this->assertSame(TaxLiability::ReverseCharge, TaxLiability::fromLegacy('reverse charge'));
        $this->assertSame(TaxLiability::ReverseCharge, TaxLiability::fromLegacy('reverse_charge'));
    }

    public function test_trip_expense_type_labels(): void
    {
        $cases = [
            [TripExpenseType::Diesel, 'Diesel'],
            [TripExpenseType::Toll, 'Toll'],
            [TripExpenseType::DriverExpense, 'Driver Expense'],
            [TripExpenseType::LoadingCharges, 'Loading Charges'],
            [TripExpenseType::UnloadingCharges, 'Unloading Charges'],
            [TripExpenseType::Repair, 'Repair'],
            [TripExpenseType::FoodExpense, 'Food Expense'],
            [TripExpenseType::Parking, 'Parking'],
            [TripExpenseType::RtoExpense, 'RTO'],
            [TripExpenseType::PermitBorderTax, 'Permit / border tax'],
            [TripExpenseType::Other, 'Other'],
        ];

        foreach ($cases as [$case, $expectedLabel]) {
            $this->assertSame($expectedLabel, $case->label());
        }
    }

    public function test_invoice_document_type_helpers_and_factory(): void
    {
        $this->assertSame('Tax Invoice', InvoiceDocumentType::TaxInvoice->label());
        $this->assertSame('Tax Invoice (RCM)', InvoiceDocumentType::TaxInvoiceRcm->label());
        $this->assertSame('Bill of Supply', InvoiceDocumentType::BillOfSupply->label());
        $this->assertSame('Bill/Invoice', InvoiceDocumentType::BillInvoice->label());

        $this->assertTrue(InvoiceDocumentType::TaxInvoice->collectsGst());
        $this->assertFalse(InvoiceDocumentType::TaxInvoiceRcm->collectsGst());

        $this->assertTrue(InvoiceDocumentType::TaxInvoice->displaysGst());
        $this->assertTrue(InvoiceDocumentType::TaxInvoiceRcm->displaysGst());
        $this->assertFalse(InvoiceDocumentType::BillOfSupply->displaysGst());

        // Factory from GST settings
        $this->assertSame(
            InvoiceDocumentType::BillOfSupply,
            InvoiceDocumentType::fromGstSettings(GstRegistrationType::Composition, null)
        );
        $this->assertSame(
            InvoiceDocumentType::BillInvoice,
            InvoiceDocumentType::fromGstSettings(GstRegistrationType::Unregistered, null)
        );
        $this->assertSame(
            InvoiceDocumentType::TaxInvoiceRcm,
            InvoiceDocumentType::fromGstSettings(GstRegistrationType::Regular, TaxLiability::ReverseCharge)
        );
        $this->assertSame(
            InvoiceDocumentType::TaxInvoice,
            InvoiceDocumentType::fromGstSettings(GstRegistrationType::Regular, TaxLiability::ForwardCharge)
        );
        $this->assertSame(
            InvoiceDocumentType::BillInvoice,
            InvoiceDocumentType::fromGstSettings(null, null)
        );
    }

    public function test_pickup_delivery_status_labels(): void
    {
        $this->assertSame('Pending', PickupDeliveryStatus::Pending->label());
        $this->assertSame('Partial', PickupDeliveryStatus::Partial->label());
        $this->assertSame('Delivered', PickupDeliveryStatus::Delivered->label());
    }

    public function test_battery_status_and_removal_reasons(): void
    {
        foreach (BatteryStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
        }

        foreach (BatteryRemovalReason::cases() as $reason) {
            $this->assertNotEmpty($reason->label());
        }
    }

    public function test_tyre_position_and_vehicle_document_type(): void
    {
        foreach (TyrePosition::cases() as $pos) {
            $this->assertNotEmpty($pos->label());
        }

        foreach (VehicleDocumentType::cases() as $docType) {
            $this->assertNotEmpty($docType->label());
        }
    }
}
