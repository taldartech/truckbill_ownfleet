<?php

namespace App\Http\Requests;

use App\GoodsType;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoodsTypeRequest extends FormRequest
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
        $goodsType = $this->route('goods_type');

        return $goodsType instanceof GoodsType
            && ($this->user()?->can('update', $goodsType) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $goodsType = $this->route('goods_type');
        return [
            'name' => [
                'required',
                'string',
                'max:100',

                Rule::unique('goods_types', 'name')
                    ->where(
                        fn($query) => $query->where(
                            'tenant_id',
                            CurrentTenant::id(),
                        ),
                    )
                    ->ignore($goodsType?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Goods type name is required.',
            'name.unique' => 'This goods type already exists.',
        ];
    }
}
