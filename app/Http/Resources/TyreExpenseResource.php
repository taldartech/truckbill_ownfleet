<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_type' => $this->expense_type?->value,
            'amount' => (float) $this->amount,
            'expense_date' => $this->expense_date?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
