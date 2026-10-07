export const INVOICE_PAYMENT_METHOD_OPTIONS = Object.freeze([
    { value: 'bank_transfer', label: 'Bank Transfer / NEFT / RTGS' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'cash', label: 'Cash' },
    { value: 'upi', label: 'UPI' },
    { value: 'other', label: 'Other' },
]);

export function invoicePaymentMethodLabel(method) {
    return INVOICE_PAYMENT_METHOD_OPTIONS.find((option) => option.value === method)?.label ?? method ?? '';
}
