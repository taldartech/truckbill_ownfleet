<?php

namespace App\Http\Requests;

use App\Driver;
use App\Enums\DriverAdvanceEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverAdvanceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $driver = $this->route('driver');

        return $driver instanceof Driver
            && ($this->user()?->can('update', $driver) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_type' => ['required', Rule::enum(DriverAdvanceEntryType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'entry_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
