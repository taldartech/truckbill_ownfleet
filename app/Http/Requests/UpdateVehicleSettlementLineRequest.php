<?php

namespace App\Http\Requests;

use App\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleSettlementLineRequest extends FormRequest
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
            'month' => ['required', 'date_format:Y-m'],
            'vehicle_id' => ['required', 'uuid'],
            'row_key' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric'],
        ];
    }
}
