<?php

namespace App\Http\Requests;

use App\Enums\TyreStatus;
use App\Enums\TyreType;
use App\Support\CurrentTenant;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTyreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tyre::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serial_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('tyres', 'serial_number')->where(fn ($query) => $query->where('tenant_id', CurrentTenant::id())),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'warranty_details' => ['nullable', 'string', 'max:2000'],
            'tyre_type' => ['nullable', Rule::enum(TyreType::class)],
            'current_km' => ['nullable', 'numeric', 'min:0'],
            'expected_life_km' => ['nullable', 'numeric', 'min:1000'],
            'status' => ['nullable', Rule::enum(TyreStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
