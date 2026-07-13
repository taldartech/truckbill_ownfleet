<?php

namespace Database\Factories;

use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TaxLiability;
use App\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $freight = fake()->randomFloat(2, 1000, 50000);

        return [
            'invoice_number' => 'INV-BR001-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'registration_type' => GstRegistrationType::Regular,
            'tax_liability' => TaxLiability::ForwardCharge,
            'document_type' => InvoiceDocumentType::TaxInvoice,
            'gst_rate' => 5,
            'freight_amount' => $freight,
            'cgst_rate' => 2.5,
            'cgst_amount' => round($freight * 0.025, 2),
            'sgst_rate' => 2.5,
            'sgst_amount' => round($freight * 0.025, 2),
            'igst_rate' => 0,
            'igst_amount' => 0,
            'gst_amount' => round($freight * 0.05, 2),
            'total_amount' => round($freight * 1.05, 2),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payment_status' => InvoicePaymentStatus::Pending,
            'paid_amount' => 0,
        ];
    }
}
