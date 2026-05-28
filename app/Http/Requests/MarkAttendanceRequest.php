<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'primary_driver_id' => ['required', 'uuid', 'exists:drivers,id'],
            'temporary_driver_id' => ['nullable', 'uuid', 'exists:drivers,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'string', 'max:50'],
            'wage_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
