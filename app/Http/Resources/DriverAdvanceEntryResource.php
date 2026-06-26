<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\DriverAdvanceEntry */
class DriverAdvanceEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'entry_type' => $this->entry_type->value,
            'amount' => $this->amount,
            'entry_date' => $this->entry_date?->toDateString(),
            'remarks' => $this->remarks,
            'created_at' => ApiDateTime::toIso($this->created_at),
        ];
    }
}
