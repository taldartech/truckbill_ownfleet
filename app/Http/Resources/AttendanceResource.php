<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
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
            'wage_owner_driver_id' => $this->wage_owner_driver_id,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'wage_amount' => $this->wage_amount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'primary_driver' => new DriverResource($this->whenLoaded('primaryDriver')),
            'temporary_driver' => new DriverResource($this->whenLoaded('temporaryDriver')),
            'wage_owner_driver' => new DriverResource($this->whenLoaded('wageOwnerDriver')),
        ];
    }
}
