<?php

namespace App\Http\Requests;

use App\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMonthlySettlementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('markMonthlyWagePaid', Attendance::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gross_earning' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'absent_deduction' => ['nullable', 'numeric', 'min:0'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'extra_deductions' => ['nullable', 'numeric', 'min:0'],
            'hra' => ['nullable', 'numeric', 'min:0'],
            'holiday_allowance' => ['nullable', 'numeric', 'min:0'],
            'pf' => ['nullable', 'numeric', 'min:0'],
            'esi' => ['nullable', 'numeric', 'min:0'],
            'other_deduction' => ['nullable', 'numeric', 'min:0'],
            'round_net' => ['nullable', 'boolean'],
            'advance_deducted' => ['nullable', 'numeric', 'min:0'],
            'diesel_deduction' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
