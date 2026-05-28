<?php

namespace App\Http\Requests;

use App\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
