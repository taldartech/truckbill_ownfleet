<?php

namespace App\Http\Requests;

use App\Battery;
use Illuminate\Foundation\Http\FormRequest;

class ScrapBatteryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $battery = $this->route('battery');

        return $battery instanceof Battery && ($this->user()?->can('update', $battery) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scrapped_at' => ['nullable', 'date', 'before_or_equal:today'],
            'scrap_notes' => ['nullable', 'string', 'max:2000'],
            'scrap_sale_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
