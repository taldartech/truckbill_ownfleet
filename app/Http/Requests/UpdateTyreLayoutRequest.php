<?php

namespace App\Http\Requests;

use App\Support\CurrentTenant;
use App\TyreLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTyreLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TyreLayout|null $layout */
        $layout = $this->route('tyre_layout');

        return $layout !== null && ($this->user()?->can('update', $layout) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TyreLayout $layout */
        $layout = $this->route('tyre_layout');
        $tenantId = CurrentTenant::id();

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('tyre_layouts', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($layout->id),
            ],
            'layout_type' => ['sometimes', Rule::in([TyreLayout::TYPE_STANDARD, TyreLayout::TYPE_LEGACY_DUAL_REAR])],
            'steer_axles' => ['sometimes', 'integer', 'min:0', 'max:2'],
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
