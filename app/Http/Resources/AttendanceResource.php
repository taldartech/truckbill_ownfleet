<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'primary_driver_id' => $this->primary_driver_id,
            'temporary_driver_id' => $this->temporary_driver_id,
            'temporary_assignment_remarks' => $this->temporary_assignment_remarks,
            'wage_owner_driver_id' => $this->wage_owner_driver_id,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'marked_via' => $this->marked_via?->value ?? $this->marked_via,
            'wage_amount' => $this->wage_amount,
            'substitute_payment_amount' => $this->substitute_payment_amount,
            'wage_payment_status' => $this->wage_payment_status?->value ?? $this->wage_payment_status,
            'wage_paid_at' => ApiDateTime::toIso($this->wage_paid_at),
            'wage_transfer_notes' => $this->wage_transfer_notes,
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'primary_driver' => new DriverResource($this->whenLoaded('primaryDriver')),
            'temporary_driver' => new DriverResource($this->whenLoaded('temporaryDriver')),
            'wage_owner_driver' => new DriverResource($this->whenLoaded('wageOwnerDriver')),
        ];
    }
}
