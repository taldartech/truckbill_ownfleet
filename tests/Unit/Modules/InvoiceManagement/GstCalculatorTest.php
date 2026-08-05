<?php

namespace Tests\Unit\Modules\InvoiceManagement;

use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\TaxLiability;
use App\Modules\InvoiceManagement\Services\InvoiceService;
use Tests\TestCase;

class GstCalculatorTest extends TestCase
{
    private function calculator(): InvoiceService
    {
        return $this->app->make(InvoiceService::class);
    }

    public function test_forward_charge_same_state_splits_cgst_sgst_and_collects_gst(): void
    {
        $result = $this->calculator()->calculateGst(
            [
                'registration_type' => GstRegistrationType::Regular,
                'tax_liability' => TaxLiability::ForwardCharge,
                'state_id' => 1,
                'state_code' => 'MH',
            ],
            freightAmount: 10000,
            gstRate: 5,
            customerStateId: 1,
            customerStateCode: 'MH',
        );

        $this->assertSame(InvoiceDocumentType::TaxInvoice, $result['document_type']);
        $this->assertTrue($result['collects_gst']);
        $this->assertSame(2.5, $result['cgst_rate']);
        $this->assertSame(2.5, $result['sgst_rate']);
        $this->assertSame(250.0, $result['cgst_amount']);
        $this->assertSame(250.0, $result['sgst_amount']);
        $this->assertSame(0.0, $result['igst_amount']);
        $this->assertSame(10500.0, $result['total_amount']);
    }

    public function test_forward_charge_different_state_uses_igst(): void
    {
        $result = $this->calculator()->calculateGst(
            [
                'registration_type' => GstRegistrationType::Regular,
                'tax_liability' => TaxLiability::ForwardCharge,
                'state_id' => 1,
                'state_code' => 'MH',
            ],
            freightAmount: 10000,
            gstRate: 5,
            customerStateId: 2,
            customerStateCode: 'GJ',
        );

        $this->assertSame(5.0, $result['igst_rate']);
        $this->assertSame(500.0, $result['igst_amount']);
        $this->assertSame(0.0, $result['cgst_amount']);
        $this->assertSame(10500.0, $result['total_amount']);
    }

    public function test_reverse_charge_displays_gst_but_charges_freight_only(): void
    {
        $result = $this->calculator()->calculateGst(
            [
                'registration_type' => GstRegistrationType::Regular,
                'tax_liability' => TaxLiability::ReverseCharge,
                'state_id' => 1,
                'state_code' => 'MH',
            ],
            freightAmount: 10000,
            gstRate: 5,
            customerStateId: 1,
            customerStateCode: 'MH',
        );

        $this->assertSame(InvoiceDocumentType::TaxInvoiceRcm, $result['document_type']);
        $this->assertTrue($result['displays_gst']);
        $this->assertFalse($result['collects_gst']);
        $this->assertSame(500.0, $result['gst_amount']);
        $this->assertSame(10000.0, $result['total_amount']);
    }

    public function test_composition_and_unregistered_hide_gst_and_use_freight_only(): void
    {
        $composition = $this->calculator()->calculateGst(
            [
                'registration_type' => GstRegistrationType::Composition,
                'tax_liability' => null,
                'state_id' => 1,
                'state_code' => 'MH',
            ],
            freightAmount: 8000,
            gstRate: 5,
            customerStateId: 1,
            customerStateCode: 'MH',
        );

        $this->assertSame(InvoiceDocumentType::BillOfSupply, $composition['document_type']);
        $this->assertFalse($composition['displays_gst']);
        $this->assertSame(0.0, $composition['gst_amount']);
        $this->assertSame(8000.0, $composition['total_amount']);

        $unregistered = $this->calculator()->calculateGst(
            [
                'registration_type' => GstRegistrationType::Unregistered,
                'tax_liability' => null,
                'state_id' => null,
                'state_code' => null,
            ],
            freightAmount: 8000,
            gstRate: 5,
            customerStateId: 1,
            customerStateCode: 'MH',
        );

        $this->assertSame(InvoiceDocumentType::BillInvoice, $unregistered['document_type']);
        $this->assertSame(8000.0, $unregistered['total_amount']);
    }

    public function test_broker_customer_skips_gst_even_for_regular_forward_charge(): void
    {
        $result = $this->calculator()->calculateGst(
            [
                'registration_type' => GstRegistrationType::Regular,
                'tax_liability' => TaxLiability::ForwardCharge,
                'state_id' => 1,
                'state_code' => 'MH',
            ],
            freightAmount: 10000,
            gstRate: 5,
            customerStateId: 1,
            customerStateCode: 'MH',
            isBroker: true,
        );

        $this->assertSame(InvoiceDocumentType::BillInvoice, $result['document_type']);
        $this->assertFalse($result['displays_gst']);
        $this->assertFalse($result['collects_gst']);
        $this->assertSame(0.0, $result['gst_amount']);
        $this->assertSame(0.0, $result['cgst_amount']);
        $this->assertSame(0.0, $result['sgst_amount']);
        $this->assertSame(0.0, $result['igst_amount']);
        $this->assertSame(10000.0, $result['total_amount']);
    }
}
