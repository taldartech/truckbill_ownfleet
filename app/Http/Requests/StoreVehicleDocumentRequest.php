<?php

namespace App\Http\Requests;

use App\Enums\VehicleDocumentType;
use App\VehicleDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'document_type' => ['required', Rule::enum(VehicleDocumentType::class)],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', $mimes)],
            'file_url' => ['nullable', 'string', 'max:500'],
            'reminder_days_before' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }
}
