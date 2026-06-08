<?php

namespace App\Http\Requests;

use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;

class ScrapTyreRequest extends FormRequest
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
        return [
            'scrapped_at' => ['nullable', 'date'],
            'scrap_notes' => ['nullable', 'string', 'max:2000'],
            'scrap_sale_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
