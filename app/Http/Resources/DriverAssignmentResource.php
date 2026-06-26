<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasPaidAttendance = app(DriverAssignmentService::class)->hasPaidAttendanceInPeriod($this->resource);
        $isPeriodLocked = app(DriverAssignmentService::class)->isAssignmentPeriodLocked($this->resource);

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'driver_id' => $this->driver_id,
            'vehicle_id' => $this->vehicle_id,
            'assigned_from' => $this->assigned_from?->toDateString(),
            'assigned_to' => $this->assigned_to?->toDateString(),
            'is_primary' => (bool) $this->is_primary,
            'has_paid_attendance' => $hasPaidAttendance || $isPeriodLocked,
            'is_period_locked' => $isPeriodLocked,
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
            'driver' => new DriverResource($this->whenLoaded('driver')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
        ];
    }
}
