<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAuditLog extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'invoice_id',
        'event_type',
        'payload',
        'performed_by',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
