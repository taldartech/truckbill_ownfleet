<?php

namespace App\Http\Resources;

use App\DriverAdvanceEntry;
use App\Enums\DriverAdvanceEntryType;
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
            'availability' => $this->availability ?? null,
            'created_at' => $this->created_at ? ApiDateTime::toIso($this->created_at) : null,
            'updated_at' => $this->updated_at ? ApiDateTime::toIso($this->updated_at) : null,
            'created_by_name' => $this->createdBy?->name,
            'updated_by_name' => $this->updatedBy?->name,
            'current_primary_assignment' => $this->currentPrimaryAssignment ? [
                'id' => $this->currentPrimaryAssignment->id,
                'vehicle_id' => $this->currentPrimaryAssignment->vehicle_id,
                'assigned_from' => $this->currentPrimaryAssignment->assigned_from?->toDateString(),
                'assigned_to' => $this->currentPrimaryAssignment->assigned_to?->toDateString(),
                'vehicle' => $this->currentPrimaryAssignment->vehicle ? [
                    'id' => $this->currentPrimaryAssignment->vehicle->id,
                    'registration_number' => $this->currentPrimaryAssignment->vehicle->registration_number,
                ] : null,
            ] : null,
            'assignment_summary' => $this->resolveAssignmentSummary(),
            'current_advance_balance' => $this->getAdvanceTotals()['current_advance_balance'],
            'total_advance' => $this->getAdvanceTotals()['total_advance'],
            'total_recovery' => $this->getAdvanceTotals()['total_recovery'],
            'active_trips' => $this->relationLoaded('trips') || $this->trips !== null ? $this->trips()
                ->whereIn('status', [\App\Enums\TripStatus::Pending->value, \App\Enums\TripStatus::InProgress->value])
                ->with(['vehicle'])
                ->get()
                ->map(fn ($trip) => [
                    'id' => $trip->id,
                    'status' => $trip->status,
                    'start_date' => $trip->start_date?->toDateString(),
                    'vehicle' => $trip->vehicle ? [
                        'id' => $trip->vehicle->id,
                        'registration_number' => $trip->vehicle->registration_number,
                    ] : null,
                ]) : [],
        ];
    }

    private function resolveAssignmentSummary(): string
    {
        $assignment = $this->currentPrimaryAssignment;
        if (! $assignment || ! $assignment->vehicle) {
            return '—';
        }

        $dateStr = $assignment->assigned_from?->format('d-M-Y');

        return "Primary Driver of {$assignment->vehicle->registration_number} (Assigned {$dateStr})";
    }

    private function getAdvanceTotals(): array
    {
        $totals = DriverAdvanceEntry::query()
            ->where('driver_id', $this->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as dr_total,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as cr_total',
                [DriverAdvanceEntryType::Dr->value, DriverAdvanceEntryType::Cr->value]
            )
            ->first();

        $dr = (float) ($totals->dr_total ?? 0);
        $cr = (float) ($totals->cr_total ?? 0);

        return [
            'current_advance_balance' => round($dr - $cr, 2),
            'total_advance' => round($dr, 2),
            'total_recovery' => round($cr, 2),
        ];
    }
}
