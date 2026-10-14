<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'tds_amount' => (float) ($this->tds_amount ?? 0),
            'deduction_amount' => (float) ($this->deduction_amount ?? 0),
            'deduction_reason' => $this->deduction_reason,
            'total_settled_amount' => round((float) $this->amount + (float) ($this->tds_amount ?? 0) + (float) ($this->deduction_amount ?? 0), 2),
            'paid_at' => $this->paid_at?->toDateString(),
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'created_at' => ApiDateTime::toIso($this->created_at),
        ];
    }
}
