<?php

namespace App\Enums;

enum InvoiceDocumentType: string
{
    case TaxInvoice = 'tax_invoice';
    case TaxInvoiceRcm = 'tax_invoice_rcm';
    case BillOfSupply = 'bill_of_supply';
    case BillInvoice = 'bill_invoice';

    public function label(): string
    {
        return match ($this) {
            self::TaxInvoice => 'Tax Invoice',
            self::TaxInvoiceRcm => 'Tax Invoice (RCM)',
            self::BillOfSupply => 'Bill of Supply',
            self::BillInvoice => 'Bill/Invoice',
        };
    }

    public function collectsGst(): bool
    {
        return $this === self::TaxInvoice;
    }

    public function displaysGst(): bool
    {
        return $this === self::TaxInvoice || $this === self::TaxInvoiceRcm;
    }

    public static function fromGstSettings(
        ?GstRegistrationType $registrationType,
        ?TaxLiability $taxLiability,
    ): self {
        return match ($registrationType) {
            GstRegistrationType::Composition => self::BillOfSupply,
            GstRegistrationType::Unregistered => self::BillInvoice,
            GstRegistrationType::Regular => $taxLiability === TaxLiability::ReverseCharge
                ? self::TaxInvoiceRcm
                : self::TaxInvoice,
            default => self::BillInvoice,
        };
    }
}
