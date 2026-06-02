<?php

/**
 * Fleet document storage — same relative paths on local disk and S3.
 *
 * Local (default):
 *   storage/app/fleet-documents/{tenant_id}/vehicles/{vehicle_id}/{document_type}/{uuid}.ext
 *
 * S3 (later — set FLEET_DOCUMENTS_DISK=s3_fleet_documents):
 *   s3://{bucket}/fleet-documents/{tenant_id}/vehicles/{vehicle_id}/{document_type}/{uuid}.ext
 *
 * Upload the contents of storage/app/fleet-documents/ to the bucket under fleet-documents/
 * without changing database file_path values.
 */
return [

    'disk' => env('FLEET_DOCUMENTS_DISK', 'fleet_documents'),

    'max_upload_kb' => (int) env('FLEET_DOCUMENTS_MAX_KB', 10240),

    'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

];
