<?php

namespace App\Http\Requests;

use App\Attendance;
use App\Enums\AttendanceMarkedVia;
use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Attendance|null $attendance */
        $attendance = $this->route('attendance');

        if ($attendance !== null) {
            return $this->user()?->can('update', $attendance) ?? false;
        }

        return $this->user()?->can('create', Attendance::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'primary_driver_id' => ['nullable', 'uuid', Rule::exists('drivers', 'id')->where('is_active', true)],
            'temporary_driver_id' => [
                'nullable',
                'uuid',
                Rule::exists('drivers', 'id')->where('is_active', true),
                'different:primary_driver_id',
            ],
            'attendance_date' => ['required', 'date'],
            'status' => [
                'required',
                Rule::enum(AttendanceStatus::class),
                Rule::notIn([AttendanceStatus::Present->value]),
            ],
            'marked_via' => ['nullable', Rule::enum(AttendanceMarkedVia::class)],
            'wage_amount' => ['nullable', 'numeric', 'min:0'],
            'temporary_assignment_remarks' => ['nullable', 'string', 'max:2000'],
            'wage_transfer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && is_string($this->input('status'))) {
            $this->merge([
                'status' => strtoupper($this->input('status')),
            ]);
        }
    }
}
