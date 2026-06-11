<?php

namespace App\Http\Requests;

use App\Enums\TyreType;
use App\Support\CurrentTenant;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTyreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tyre = $this->route('tyre');

        return $tyre instanceof Tyre && ($this->user()?->can('update', $tyre) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tyre $tyre */
        $tyre = $this->route('tyre');

        return [
            'serial_number' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('tyres', 'serial_number')
                    ->where(fn ($query) => $query->where('tenant_id', CurrentTenant::id()))
                    ->ignore($tyre->id),
            ],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'size' => ['sometimes', 'nullable', 'string', 'max:50'],
            'purchase_date' => ['sometimes', 'nullable', 'date'],
            'purchase_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'warranty_details' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'warranty_valid_until' => ['sometimes', 'nullable', 'date'],
            'warranty_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tyre_type' => ['sometimes', 'nullable', Rule::enum(TyreType::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_life_km' => ['sometimes', 'nullable', 'numeric', 'min:1000'],
            'current_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
