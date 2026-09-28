<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\FreightBillingStatus;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TripStatus;
use App\Enums\TripType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'vehicle_id',
        'driver_id',
        'substitute_driver_id',
        'trip_number',
        'customer_id',
        'customer_name',
        'broker_id',
        'broker_name',
        'start_station_id',
        'trip_type',
        'status',
        'start_at',
        'end_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'actual_arrival_at',
        'start_meter_reading_km',
        'end_meter_reading_km',
        'load_weight_ton',
        'planned_mileage_km_per_liter',
        'max_load_ton',
        'planned_diesel_liters',
        'diesel_rate_per_liter',
        'total_distance_km',
        'loaded_distance_km',
        'empty_distance_km',
        'total_expense_amount',
        'total_freight_amount',
        'total_custom_amount',
        'created_by',
        'updated_by',
        'archived_by',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'trip_type' => TripType::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'start_meter_reading_km' => 'decimal:2',
            'end_meter_reading_km' => 'decimal:2',
            'load_weight_ton' => 'decimal:3',
            'planned_mileage_km_per_liter' => 'decimal:2',
            'max_load_ton' => 'decimal:3',
            'planned_diesel_liters' => 'decimal:2',
            'diesel_rate_per_liter' => 'decimal:2',
            'total_distance_km' => 'decimal:2',
            'loaded_distance_km' => 'decimal:2',
            'empty_distance_km' => 'decimal:2',
            'total_expense_amount' => 'decimal:2',
            'total_freight_amount' => 'decimal:2',
            'total_custom_amount' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function substituteDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'substitute_driver_id')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function startStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'start_station_id');
    }

    public function routeLegs(): HasMany
    {
        return $this->hasMany(TripRouteLeg::class)->orderBy('sequence_no');
    }

    public function pickups(): HasMany
    {
        return $this->hasMany(TripPickup::class)->orderBy('sequence_no');
    }

    public function isMultiPoint(): bool
    {
        $type = $this->trip_type instanceof TripType
            ? $this->trip_type
            : TripType::tryFrom((string) $this->trip_type);

        return $type === TripType::MultiPoint;
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TripStatusHistory::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(TripAuditLog::class);
    }

    public function profitAmount(): float
    {
        return (float) $this->total_freight_amount
            + (float) $this->total_custom_amount
            - (float) $this->total_expense_amount;
    }

    public function hasInvoices(): bool
    {
        if (array_key_exists('invoices_exists', $this->attributes)) {
            return (bool) $this->attributes['invoices_exists'];
        }

        if ($this->relationLoaded('invoices')) {
            return $this->invoices
                ->contains(fn (Invoice $invoice): bool => ! $invoice->isCancelled());
        }

        return $this->invoices()
            ->where('payment_status', '!=', InvoicePaymentStatus::Cancelled->value)
            ->exists();
    }

    public function canGenerateInvoices(): bool
    {
        if ($this->trashed()) {
            return false;
        }

        $status = $this->status instanceof TripStatus
            ? $this->status
            : TripStatus::tryFrom((string) $this->status);

        if (! in_array($status, [TripStatus::Completed, TripStatus::Invoiced], true)) {
            return false;
        }

        $legs = $this->relationLoaded('routeLegs')
            ? $this->routeLegs
            : $this->routeLegs()->get();

        return $legs->contains(function (TripRouteLeg $leg): bool {
            if (! $leg->isRevenueLeg() || blank($leg->customer_id) || (float) $leg->freight_amount <= 0) {
                return false;
            }

            $billingStatus = $leg->billing_status instanceof FreightBillingStatus
                ? $leg->billing_status
                : FreightBillingStatus::tryFrom((string) ($leg->billing_status ?? 'unbilled'));

            return $billingStatus === null
                || $billingStatus === FreightBillingStatus::Unbilled
                || $billingStatus === FreightBillingStatus::Billed;
        });
    }
}
