<?php

namespace App\Http\Requests;

use App\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class MarkAllMonthlyWagesPaidRequest extends FormRequest
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
            'payment_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
