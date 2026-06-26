<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyDriverWageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'driver_id' => $this->driver_id,
            'month' => $this->month?->toDateString(),
            'total_days' => (int) $this->total_days,
            'payable_days' => (int) $this->payable_days,
            'total_wage' => $this->total_wage,
            'calendar_days' => (int) $this->calendar_days,
            'present_days' => (int) $this->present_days,
            'absent_days' => (int) $this->absent_days,
            'leave_days' => (int) $this->leave_days,
            'gross_earning' => $this->gross_earning,
            'bonus' => $this->bonus,
            'absent_deduction' => $this->absent_deduction,
            'base_salary' => $this->base_salary,
            'hra' => $this->hra,
            'holiday_allowance' => $this->holiday_allowance,
            'pf' => $this->pf,
            'esi' => $this->esi,
            'other_deduction' => $this->other_deduction,
            'advance_deducted' => $this->advance_deducted,
            'diesel_deduction' => $this->diesel_deduction,
            'total_advance' => $this->total_advance,
            'net_salary' => $this->net_salary,
            'round_net' => (bool) $this->round_net,
            'remarks' => $this->remarks,
            'payment_status' => $this->payment_status?->value ?? $this->payment_status,
            'paid_at' => ApiDateTime::toIso($this->paid_at),
            'payment_notes' => $this->payment_notes,
            'closed_at' => ApiDateTime::toIso($this->closed_at),
            'driver' => new DriverResource($this->whenLoaded('driver')),
        ];
    }
}
