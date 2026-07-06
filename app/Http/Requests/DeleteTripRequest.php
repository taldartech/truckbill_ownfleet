<?php

namespace App\Http\Requests;

use App\Trip;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && ($this->user()?->can('delete', $trip) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'deletion_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deletion_reason.required' => 'Please provide a reason for deleting this trip.',
            'deletion_reason.min' => 'Deletion reason must be at least 10 characters.',
            'deletion_reason.max' => 'Deletion reason cannot exceed 500 characters.',
        ];
    }
}
