<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use App\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_name' => $this->branch_name,
            'branch_address' => $this->branch_address,
            'branch_contact_number' => $this->branch_contact_number,
            'head_office' => (bool) $this->head_office,
            'trip_sequence_start' => (int) ($this->trip_sequence_start ?? 1),
            'trip_sequence_start_locked' => Trip::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $this->id)
                ->exists(),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
            ]),
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
