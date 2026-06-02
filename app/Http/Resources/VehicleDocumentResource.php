<?php

namespace App\Http\Resources;

use App\Enums\VehicleDocumentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->document_type instanceof VehicleDocumentType
            ? $this->document_type
            : VehicleDocumentType::tryFrom((string) $this->document_type);

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'document_type' => $type?->value ?? $this->document_type,
            'document_type_label' => $type?->label() ?? $this->document_type,
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
            'reminder_days_before' => $this->reminder_days_before,
            'is_expired' => $this->expiry_date !== null && $this->expiry_date->isPast(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
        ];
    }
}
