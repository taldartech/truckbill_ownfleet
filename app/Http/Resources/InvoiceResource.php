<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $routeFreightAmount = $this->when(
            $this->relationLoaded('items'),
            fn (): float => round(
                (float) $this->items
                    ->filter(fn ($item) => $item->trip_route_leg_id !== null)
                    ->sum(fn ($item) => (float) $item->freight_amount),
                2,
            ),
        );

        $customAmount = $this->when(
            $this->relationLoaded('items'),
            fn (): float => round(
                (float) $this->items
                    ->filter(fn ($item) => $item->trip_route_leg_id === null)
                    ->sum(fn ($item) => (float) $item->freight_amount),
                2,
            ),
        );

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'trip_id' => $this->trip_id,
            'customer_id' => $this->customer_id,
            'invoice_number' => $this->displayInvoiceNumber(),
            'registration_type' => $this->registration_type?->value ?? $this->registration_type,
            'tax_liability' => $this->tax_liability?->value ?? $this->tax_liability,
            'document_type' => $this->document_type?->value ?? $this->document_type,
            'document_type_label' => $this->document_type?->label(),
            'particulars_mode' => $this->particulars_mode?->value ?? $this->particulars_mode,
            'gst_rate' => (float) $this->gst_rate,
            'supplier_state_id' => $this->supplier_state_id,
            'supplier_state_code' => $this->whenLoaded('supplierState', fn () => $this->supplierState?->code),
            'customer_state_id' => $this->customer_state_id,
            'customer_state_code' => $this->whenLoaded('customerState', fn () => $this->customerState?->code)
                ?? $this->whenLoaded('customer', fn () => $this->customer?->state?->code),
            'place_of_supply' => $this->whenLoaded('customerState', fn () => $this->customerState?->code)
                ?? $this->whenLoaded('customer', fn () => $this->customer?->state?->code)
                ?? $this->whenLoaded('supplierState', fn () => $this->supplierState?->code),
            'freight_amount' => (float) $this->freight_amount,
            'route_freight_amount' => $routeFreightAmount,
            'custom_amount' => $customAmount,
            'displays_gst' => $this->document_type?->displaysGst() ?? false,
            'collects_gst' => $this->document_type?->collectsGst() ?? false,
            'can_edit_charges' => $this->canEditCharges(),
            'cgst_rate' => (float) $this->cgst_rate,
            'cgst_amount' => (float) $this->cgst_amount,
            'sgst_rate' => (float) $this->sgst_rate,
            'sgst_amount' => (float) $this->sgst_amount,
            'igst_rate' => (float) $this->igst_rate,
            'igst_amount' => (float) $this->igst_amount,
            'gst_amount' => (float) $this->gst_amount,
            'total_amount' => (float) $this->total_amount,
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'paid_amount' => (float) $this->paid_amount,
            'remaining_amount' => $this->remainingAmount(),
            'total_amount_received' => $this->totalAmountReceived(),
            'total_tds_deducted' => $this->totalTdsDeducted(),
            'total_claims_deducted' => $this->totalClaimsDeducted(),
            'is_cancelled' => $this->isCancelled(),
            'cancelled_at' => $this->when($this->isCancelled(), fn () => ApiDateTime::toIso($this->cancelled_at)),
            'cancellation_reason' => $this->when($this->isCancelled(), $this->cancellation_reason),
            'cancelled_by' => $this->when(
                $this->isCancelled() && $this->relationLoaded('cancelledBy'),
                fn () => $this->cancelledBy ? [
                    'id' => $this->cancelledBy->id,
                    'name' => $this->cancelledBy->name,
                ] : null,
            ),
            'is_overdue' => $this->isOverdue(),
            'can_delete' => $this->canBeSoftDeleted(),
            'is_archived' => $this->trashed(),
            'archived_at' => $this->when($this->trashed(), fn () => ApiDateTime::toIso($this->deleted_at)),
            'deletion_reason' => $this->when($this->trashed(), $this->deletion_reason),
            'deleted_by' => $this->when(
                $this->trashed() && $this->relationLoaded('deletedBy'),
                fn () => $this->deletedBy ? [
                    'id' => $this->deletedBy->id,
                    'name' => $this->deletedBy->name,
                ] : null,
            ),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'gst_number' => $this->customer?->gst_number,
                'pan_number' => $this->customer?->pan_number,
                'state_id' => $this->customer?->state_id,
                'state_code' => $this->customer?->state?->code,
                'state_name' => $this->customer?->state?->name,
            ]),
            'trip' => $this->whenLoaded('trip', fn () => [
                'id' => $this->trip?->id,
                'trip_number' => $this->trip?->trip_number,
                'status' => $this->trip?->status?->value ?? $this->trip?->status,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'invoice_id' => $item->invoice_id,
                'trip_route_leg_id' => $item->trip_route_leg_id,
                'is_route_freight' => $item->trip_route_leg_id !== null,
                'is_additional_charge' => $item->trip_route_leg_id === null,
                'sequence_no' => (int) $item->sequence_no,
                'from_location' => $item->from_location,
                'to_location' => $item->to_location,
                'goods_name' => $item->goods_name,
                'weight_ton' => (float) $item->weight_ton,
                'freight_amount' => (float) $item->freight_amount,
            ])->values()->all()),
            'audit_logs' => $this->whenLoaded('auditLogs', fn () => $this->auditLogs->map(fn ($log) => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'payload' => $log->payload,
                'performed_at' => ApiDateTime::toIso($log->performed_at),
                'performed_by' => $log->relationLoaded('performer') && $log->performer ? [
                    'id' => $log->performer->id,
                    'name' => $log->performer->name,
                ] : null,
            ])->values()->all()),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
