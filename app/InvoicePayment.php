<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    /** @use HasFactory<\Database\Factories\InvoicePaymentFactory> */
    use BranchScoped;

    use HasFactory;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'invoice_id',
        'amount',
        'tds_amount',
        'deduction_amount',
        'deduction_reason',
        'paid_at',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tds_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function getTotalSettledAmountAttribute(): float
    {
        return round((float) $this->amount + (float) ($this->tds_amount ?? 0) + (float) ($this->deduction_amount ?? 0), 2);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
