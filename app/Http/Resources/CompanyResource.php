<?php

namespace App\Http\Resources;

use App\Enums\PayrollPayDaysMode;
use App\Support\ApiDateTime;
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
            'registration_type' => $this->registration_type?->value ?? $this->registration_type,
            'tax_liability' => $this->tax_liability?->value ?? $this->tax_liability,
            'state_id' => $this->state_id,
            'gst_rate' => $this->gst_rate !== null ? (float) $this->gst_rate : null,
            'state_name' => $this->whenLoaded('state', fn () => $this->state?->name),
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'company_logo' => $this->company_logo,
            'logo_url' => $this->logoPublicUrl(),
            'document_color' => $this->documentColor(),
            'plan_end_date' => $this->plan_end_date?->toDateString(),
            'plan_end_date_formatted' => $this->plan_end_date
                ? Carbon::parse($this->plan_end_date)->format('d M, Y')
                : null,
            'lr_credits' => $this->lr_credits,
            'truck_credits' => $this->truck_credits,
            'payroll_pay_days_mode' => $this->resolvePayrollPayDaysMode(),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
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
