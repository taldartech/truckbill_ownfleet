<?php

namespace App\Support;

use App\VehicleDocument;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FleetDocumentStorage
{
    public static function diskName(): string
    {
        return (string) config('fleet-documents.disk', 'fleet_documents');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * Relative path stored in DB — identical on local disk and S3 prefix.
     */
    public static function buildRelativePath(
        string $tenantId,
        string $vehicleId,
        string $documentType,
        UploadedFile $file,
    ): string {
        $folder = Str::slug($documentType, '_');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return sprintf(
            '%s/vehicles/%s/%s/%s.%s',
            $tenantId,
            $vehicleId,
            $folder,
            (string) Str::uuid(),
            $extension,
        );
    }

    /**
     * @return array{file_path: string, original_filename: string, mime_type: string|null, file_size: int}
     */
    public static function store(
        string $tenantId,
        string $vehicleId,
        string $documentType,
        UploadedFile $file,
    ): array {
        $relativePath = self::buildRelativePath($tenantId, $vehicleId, $documentType, $file);

        self::disk()->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath),
        );

        return [
            'file_path' => $relativePath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (self::disk()->exists($relativePath)) {
            self::disk()->delete($relativePath);
        }
    }

    public static function replace(
        VehicleDocument $document,
        UploadedFile $file,
    ): array {
        self::delete($document->file_path);

        return self::store(
            (string) $document->tenant_id,
            (string) $document->vehicle_id,
            $document->document_type instanceof \BackedEnum
                ? $document->document_type->value
                : (string) $document->document_type,
            $file,
        );
    }

    public static function downloadResponse(VehicleDocument $document): StreamedResponse
    {
        if ($document->file_path === null || ! self::disk()->exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        return self::disk()->download(
            $document->file_path,
            $document->original_filename ?? basename($document->file_path),
        );
    }

    public static function previewResponse(VehicleDocument $document): StreamedResponse
    {
        if ($document->file_path === null || ! self::disk()->exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        $filename = $document->original_filename ?? basename($document->file_path);
        $safeFilename = str_replace('"', '', $filename);
        $headers = [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => sprintf('inline; filename="%s"', $safeFilename),
        ];

        return self::disk()->response($document->file_path, $filename, $headers);
    }
}
