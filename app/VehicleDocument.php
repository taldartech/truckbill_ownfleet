<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\VehicleDocumentType;
use App\Support\FleetDocumentStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'document_type',
        'issue_date',
        'expiry_date',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'file_url',
        'reminder_days_before',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => VehicleDocumentType::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function hasStoredFile(): bool
    {
        return $this->file_path !== null && $this->file_path !== '';
    }

    protected static function booted(): void
    {
        static::deleting(function (VehicleDocument $document): void {
            FleetDocumentStorage::delete($document->file_path);
        });
    }
}
