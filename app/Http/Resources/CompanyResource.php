<?php

namespace App\Http\Resources;

use App\Enums\PayrollPayDaysMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->id,
            'company_id' => $this->id,
            'owner_user_id' => $this->owner_user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_name' => $this->short_name,
            'timezone' => $this->timezone,
            'is_active' => (bool) $this->is_active,
            'is_blocked' => (bool) $this->is_blocked,
            'gst_number' => $this->gst_number,
            'pan_number' => $this->pan_number,
            'gst_scheme' => $this->gst_scheme,
            'gst_type' => $this->gst_type,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'plan_end_date' => $this->plan_end_date?->toDateString(),
            'plan_end_date_formatted' => $this->plan_end_date
                ? Carbon::parse($this->plan_end_date)->format('d M, Y')
                : null,
            'lr_credits' => $this->lr_credits,
            'truck_credits' => $this->truck_credits,
            'payroll_pay_days_mode' => $this->resolvePayrollPayDaysMode(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function resolvePayrollPayDaysMode(): string
    {
        $mode = $this->payroll_pay_days_mode ?? PayrollPayDaysMode::Calendar;

        if ($mode instanceof PayrollPayDaysMode) {
            return $mode->value;
        }

        return PayrollPayDaysMode::tryFrom((string) $mode)?->value ?? PayrollPayDaysMode::Calendar->value;
    }
}
