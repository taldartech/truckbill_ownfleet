<?php

namespace App\Http\Requests;

use App\GoodsType;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
