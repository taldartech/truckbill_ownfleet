<?php

namespace App\Http\Requests;

use App\Support\CurrentTenant;
use App\VehicleDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var VehicleDocument|null $vehicleDocument */
        $vehicleDocument = $this->route('vehicle_document');

        if ($vehicleDocument !== null) {
            return $this->user()?->can('update', $vehicleDocument) ?? false;
        }

        return $this->user()?->can('create', VehicleDocument::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('fleet-documents.max_upload_kb', 10240);
        $mimes = config('fleet-documents.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp']);

        /** @var VehicleDocument|null $vehicleDocument */
        $vehicleDocument = $this->route('vehicle_document');
        $tenantId = CurrentTenant::id();

        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'document_type' => [
                'required',
                'string',
                'max:64',
                Rule::exists('document_types', 'code')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true),
                ),
                Rule::unique('vehicle_documents', 'document_type')
                    ->where(fn ($query) => $query
                        ->where('vehicle_id', $this->input('vehicle_id'))
                        ->where('tenant_id', $tenantId))
                    ->ignore($vehicleDocument?->id),
            ],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after:issue_date'],
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', $mimes)],
            'file_url' => ['nullable', 'string', 'max:500'],
            'reminder_days_before' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->filled(['issue_date', 'expiry_date'])) {
                return;
            }

            $issueDate = Carbon::parse((string) $this->input('issue_date'))->startOfDay();
            $expiryDate = Carbon::parse((string) $this->input('expiry_date'))->startOfDay();
            $validityDays = (int) $issueDate->diffInDays($expiryDate);

            if ($validityDays < 1) {
                return;
            }

            $reminderDays = $this->input('reminder_days_before');

            if ($reminderDays !== null && (int) $reminderDays > $validityDays) {
                $validator->errors()->add(
                    'reminder_days_before',
                    'Reminder days cannot exceed the document validity period.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'issue_date.required' => 'Issue date is required.',
            'expiry_date.required' => 'Expiry date is required.',
            'expiry_date.after' => 'Expiry date must be after the issue date.',
            'document_type.unique' => 'This document type already exists for this truck. Please renew the existing document.',
            'document_type.exists' => 'Selected document type is invalid.',
        ];
    }
}
