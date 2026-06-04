<?php

namespace App\Http\Requests;

use App\Enums\TyrePosition;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTyreRotationRequest extends FormRequest
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
            'rotation_date' => ['required', 'date'],
            'previous_position' => ['required', Rule::enum(TyrePosition::class)],
            'new_position' => ['required', Rule::enum(TyrePosition::class), 'different:previous_position'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'km_at_rotation' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
