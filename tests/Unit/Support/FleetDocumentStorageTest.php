<?php

namespace Tests\Unit\Support;

use App\Enums\VehicleDocumentType;
use App\Support\FleetDocumentStorage;
use App\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FleetDocumentStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(FleetDocumentStorage::diskName());
    }

    public function test_store_and_delete_fleet_document(): void
    {
        $file = UploadedFile::fake()->create('registration.pdf', 100, 'application/pdf');

        $result = FleetDocumentStorage::store('tenant-1', 'truck-10', 'rc', $file);

        $this->assertArrayHasKey('file_path', $result);
        $this->assertTrue(Storage::disk(FleetDocumentStorage::diskName())->exists($result['file_path']));

        FleetDocumentStorage::delete($result['file_path']);
        $this->assertFalse(Storage::disk(FleetDocumentStorage::diskName())->exists($result['file_path']));

        // Safe delete for null/empty
        FleetDocumentStorage::delete(null);
        FleetDocumentStorage::delete('');
    }

    public function test_replace_deletes_old_file_and_stores_new(): void
    {
        $file1 = UploadedFile::fake()->create('old.pdf', 50, 'application/pdf');
        $stored = FleetDocumentStorage::store('tenant-rep', 'truck-rep', 'fitness_certificate', $file1);

        $doc = new VehicleDocument([
            'tenant_id' => 'tenant-rep',
            'vehicle_id' => 'truck-rep',
            'document_type' => VehicleDocumentType::FitnessCertificate,
            'file_path' => $stored['file_path'],
        ]);

        $file2 = UploadedFile::fake()->create('new.pdf', 80, 'application/pdf');
        $newStored = FleetDocumentStorage::replace($doc, $file2);

        $this->assertFalse(Storage::disk(FleetDocumentStorage::diskName())->exists($stored['file_path']));
        $this->assertTrue(Storage::disk(FleetDocumentStorage::diskName())->exists($newStored['file_path']));
    }

    public function test_download_and_preview_responses_throw_404_when_missing(): void
    {
        $doc = new VehicleDocument(['file_path' => 'non_existent_file.pdf']);

        $caught = 0;
        try {
            FleetDocumentStorage::downloadResponse($doc);
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $caught++;
        }

        try {
            FleetDocumentStorage::previewResponse($doc);
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $caught++;
        }

        $this->assertSame(2, $caught);
    }

    public function test_download_and_preview_responses_return_streamed_response_when_file_exists(): void
    {
        $file = UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf');
        $stored = FleetDocumentStorage::store('tenant-dl', 'truck-dl', 'permit', $file);

        $doc = new VehicleDocument([
            'tenant_id' => 'tenant-dl',
            'vehicle_id' => 'truck-dl',
            'document_type' => 'permit',
            'file_path' => $stored['file_path'],
            'original_filename' => 'permit.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $download = FleetDocumentStorage::downloadResponse($doc);
        $this->assertInstanceOf(StreamedResponse::class, $download);

        $preview = FleetDocumentStorage::previewResponse($doc);
        $this->assertInstanceOf(StreamedResponse::class, $preview);
    }
}
