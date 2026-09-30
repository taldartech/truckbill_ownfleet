<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuperAdminDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'company_id' => [
                'nullable',
                'string',
                Rule::exists('tenants', 'id')->where('owner_user_id', $this->user()?->id),
            ],
            'trend_months' => ['nullable', 'integer', Rule::in([6, 12])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_date.after_or_equal' => 'To Date cannot be earlier than From Date.',
            'company_id.exists' => 'Select one of your companies.',
            'trend_months.in' => 'Trend can be shown for the last 6 or 12 months.',
        ];
    }
}
