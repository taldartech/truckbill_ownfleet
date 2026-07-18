<?php

namespace App\Http\Requests;

use App\Trip;
use Illuminate\Foundation\Http\FormRequest;

class RestoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && ($this->user()?->can('restore', $trip) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
