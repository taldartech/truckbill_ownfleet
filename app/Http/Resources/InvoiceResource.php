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
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'trip_id' => $this->trip_id,
            'customer_id' => $this->customer_id,
            'invoice_number' => $this->invoice_number,
            'registration_type' => $this->registration_type?->value ?? $this->registration_type,
            'tax_liability' => $this->tax_liability?->value ?? $this->tax_liability,
            'document_type' => $this->document_type?->value ?? $this->document_type,
            'document_type_label' => $this->document_type?->label(),
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
            'is_cancelled' => $this->isCancelled(),
            'is_overdue' => $this->isOverdue(),
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
                'sequence_no' => (int) $item->sequence_no,
                'from_location' => $item->from_location,
                'to_location' => $item->to_location,
                'goods_name' => $item->goods_name,
                'weight_ton' => (float) $item->weight_ton,
                'freight_amount' => (float) $item->freight_amount,
            ])->values()->all()),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
