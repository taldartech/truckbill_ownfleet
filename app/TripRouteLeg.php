<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\FreightBillingStatus;
use App\Enums\FreightPaymentStatus;
use App\Enums\RouteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripRouteLeg extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'trip_id',
        'customer_id',
        'from_station_id',
        'to_station_id',
        'from_city',
        'from_address',
        'to_city',
        'to_address',
        'route_type',
        'sequence_no',
        'distance_km',
        'ewaybill_no',
        'ewaybill_expiry',
        'goods_value',
        'goods_name',
        'pickup_date',
        'no_of_units',
        'consignor',
        'consignee',
        'freight_rate',
        'weight_ton',
        'expected_mileage_km_per_liter',
        'invoice_number',
        'lr_number',
        'remarks',
        'freight_amount',
        'advance_received',
        'balance_amount',
        'payment_status',
        'billing_status',
    ];

    protected function casts(): array
    {
        return [
            'route_type' => RouteType::class,
            'distance_km' => 'decimal:2',
            'goods_value' => 'decimal:2',
            'pickup_date' => 'date',
            'ewaybill_expiry' => 'date',
            'no_of_units' => 'integer',
            'freight_rate' => 'decimal:2',
            'weight_ton' => 'decimal:3',
            'expected_mileage_km_per_liter' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'advance_received' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'payment_status' => FreightPaymentStatus::class,
            'billing_status' => FreightBillingStatus::class,
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

    public function fromStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }

    public function toStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function isLoaded(): bool
    {
        return (float) ($this->weight_ton ?? 0) > 0;
    }

    public function isEmpty(): bool
    {
        return (float) ($this->weight_ton ?? 0) <= 0;
    }

    public function isRevenueLeg(): bool
    {
        return $this->isLoaded();
    }
}
