<?php

namespace App\Http\Requests;

use App\Trip;
use Illuminate\Foundation\Http\FormRequest;

class ArchiveTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip
            && ($this->user()?->can('archive', $trip) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archive_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archive_reason.required' => 'Please provide a reason for archiving this trip.',
            'archive_reason.min' => 'Archive reason must be at least 10 characters.',
            'archive_reason.max' => 'Archive reason cannot exceed 500 characters.',
        ];
    }
}
