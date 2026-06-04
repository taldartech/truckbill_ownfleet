<?php

namespace App\Http\Requests;

use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;

class StoreTyreRetreadRequest extends FormRequest
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
            'vendor' => ['required', 'string', 'max:150'],
            'cost' => ['required', 'numeric', 'min:0'],
            'retread_date' => ['required', 'date'],
            'condition_after' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
