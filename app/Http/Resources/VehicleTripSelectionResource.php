<?php

namespace App\Http\Resources;

use App\Modules\TripManagement\Services\VehicleTripEligibilityService;
use App\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleTripSelectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VehicleTripEligibilityService $eligibilityService */
        $eligibilityService = app(VehicleTripEligibilityService::class);
        $eligibility = $eligibilityService->evaluate(
            $this->resource,
            $request->filled('as_of_date') ? $request->string('as_of_date')->toString() : null,
        );

        $registrationNumber = $this->registration_number;

        return [
            'vehicle_id' => $this->id,
            'vehicle_number' => $registrationNumber,
            'registration_number' => $registrationNumber,
            'primary_driver_id' => $eligibility['primary_driver_id'],
            'primary_driver_name' => $eligibility['primary_driver_name'],
            'vehicle_status' => $this->is_active ? 'active' : 'inactive',
            'driver_status' => $eligibility['driver_status'],
            'selectable' => $eligibility['selectable'],
            'disabled_reason' => $eligibility['reason_label'],
            'reason_code' => $eligibility['reason_code'],
            'open_trip_number' => $eligibility['open_trip_number'] ?? null,
        ];
    }
}
