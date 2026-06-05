<?php

namespace App\Http\Requests;

use App\Support\CurrentTenant;
use App\TyreLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTyreLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TyreLayout::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = CurrentTenant::id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('tyre_layouts', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'layout_type' => ['required', Rule::in([TyreLayout::TYPE_STANDARD, TyreLayout::TYPE_LEGACY_DUAL_REAR])],
            'steer_axles' => ['required_if:layout_type,'.TyreLayout::TYPE_STANDARD, 'integer', 'min:0', 'max:2'],
            'dual_axles' => ['nullable', 'array'],
            'dual_axles.*' => ['integer', 'min:1', 'max:10'],
            'single_axles' => ['nullable', 'array'],
            'single_axles.*' => ['integer', 'min:1', 'max:10'],
            'spare_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
