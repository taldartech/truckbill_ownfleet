<?php

namespace App\Http\Requests;

use App\GoodsType;
use App\Support\CurrentTenant;
use App\Support\GoodsTypeDefaults;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && $this->input('name') !== null) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsType::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('goods_types', 'names')->where(
                    fn ($query) => $query->where('tenant_id', CurrentTenant::id()),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Goods type name is required.',
            'name.unique' => 'This goods type already exists.',
        ];
    }
}
