<?php

namespace App\Http\Requests;

use App\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Attendance::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'attendance_type' => ['required', Rule::in(['ABSENT', 'ABSENT_SUBSTITUTE'])],
            'temporary_driver_id' => [
                'nullable',
                'uuid',
                Rule::exists('drivers', 'id')->where('is_active', true),
                'required_if:attendance_type,ABSENT_SUBSTITUTE',
            ],
            'temporary_assignment_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
