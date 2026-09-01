<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkUpload extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'entity',
        'file_name',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'status',
        'error_file',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}