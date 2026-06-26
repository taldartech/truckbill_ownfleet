<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'license_expiry_date' => $this->license_expiry_date?->toDateString(),
            'address' => $this->address,
            'aadhaar_number' => $this->aadhaar_number,
            'pan_number' => $this->pan_number,
            'bank_account_number' => $this->bank_account_number,
            'bank_ifsc' => $this->bank_ifsc,
            'joining_date' => $this->joining_date?->toDateString(),
            'monthly_wage' => $this->daily_wage,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'daily_wage' => $this->daily_wage,
            'is_active' => (bool) $this->is_active,
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
        ];
    }
}
