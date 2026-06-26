<?php

namespace App\Http\Resources;

use App\Support\ApiDateTime;
use App\Enums\VehicleDocumentType;
use App\Support\DocumentTypeDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $code = (string) $this->document_type;
        $enumType = VehicleDocumentType::tryFrom($code);
        $label = DocumentTypeDefaults::labelFor((string) $this->tenant_id, $code)
            ?? $enumType?->label()
            ?? $code;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'document_type' => $code,
            'document_type_label' => $label,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'has_file' => $this->hasStoredFile(),
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'file_url' => $this->file_url,
            'download_url' => $this->when(
                $this->hasStoredFile(),
                fn () => route('app.api.vehicle-documents.download', $this->id),
            ),
            'preview_url' => $this->when(
                $this->hasStoredFile(),
                fn () => route('app.api.vehicle-documents.preview', $this->id),
            ),
            'reminder_days_before' => $this->reminder_days_before,
            'is_expired' => $this->expiry_date !== null && $this->expiry_date->isPast(),
            'created_at' => ApiDateTime::toIso($this->created_at),
            'updated_at' => ApiDateTime::toIso($this->updated_at),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
        ];
    }
}
