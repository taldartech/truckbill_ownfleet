<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TaxLiability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use BranchScoped;
    use HasFactory;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'trip_id',
        'customer_id',
        'invoice_number',
        'registration_type',
        'tax_liability',
        'document_type',
        'gst_rate',
        'supplier_state_id',
        'customer_state_id',
        'freight_amount',
        'cgst_rate',
        'cgst_amount',
        'sgst_rate',
        'sgst_amount',
        'igst_rate',
        'igst_amount',
        'gst_amount',
        'total_amount',
        'invoice_date',
        'due_date',
        'payment_status',
        'paid_amount',
        'deleted_by',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'registration_type' => GstRegistrationType::class,
            'tax_liability' => TaxLiability::class,
            'document_type' => InvoiceDocumentType::class,
            'payment_status' => InvoicePaymentStatus::class,
            'gst_rate' => 'decimal:3',
            'freight_amount' => 'decimal:2',
            'cgst_rate' => 'decimal:3',
            'cgst_amount' => 'decimal:2',
            'sgst_rate' => 'decimal:3',
            'sgst_amount' => 'decimal:2',
            'igst_rate' => 'decimal:3',
            'igst_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplierState(): BelongsTo
    {
        return $this->belongsTo(State::class, 'supplier_state_id');
    }

    public function customerState(): BelongsTo
    {
        return $this->belongsTo(State::class, 'customer_state_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sequence_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('paid_at');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function remainingAmount(): float
    {
        if ($this->isCancelled()) {
            return 0;
        }

        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    public function isCancelled(): bool
    {
        return $this->payment_status === InvoicePaymentStatus::Cancelled;
    }

    public function hasRecordedPayments(): bool
    {
        if ((float) $this->paid_amount > 0) {
            return true;
        }

        if ($this->relationLoaded('payments')) {
            return $this->payments->isNotEmpty();
        }

        return $this->payments()->exists();
    }

    public function isLatestForBranch(): bool
    {
        if ($this->trashed() || $this->branch_id === null) {
            return false;
        }

        $latestId = static::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('branch_id', $this->branch_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        return $latestId === $this->id;
    }

    public function canBeSoftDeleted(): bool
    {
        return ! $this->trashed()
            && ! $this->hasRecordedPayments()
            && $this->isLatestForBranch();
    }

    public function isOverdue(): bool
    {
        if ($this->due_date === null || $this->isCancelled()) {
            return false;
        }

        if ($this->payment_status === InvoicePaymentStatus::Paid) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $paid = round((float) $this->payments()->sum('amount'), 2);
        $total = round((float) $this->total_amount, 2);

        $status = match (true) {
            $paid <= 0 => InvoicePaymentStatus::Pending,
            $paid + 0.001 >= $total => InvoicePaymentStatus::Paid,
            default => InvoicePaymentStatus::Partial,
        };

        $this->forceFill([
            'paid_amount' => $paid,
            'payment_status' => $status,
        ])->save();
    }
}
