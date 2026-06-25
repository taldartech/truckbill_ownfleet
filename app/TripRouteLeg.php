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
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'trip_id',
        'customer_id',
        'from_station_id',
        'to_station_id',
        'from_location',
        'to_location',
        'from_city',
        'from_address',
        'to_city',
        'to_address',
        'route_type',
        'sequence_no',
        'distance_km',
        'goods_name',
        'consignor',
        'consignee',
        'freight_rate',
        'weight_ton',
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
            'freight_rate' => 'decimal:2',
            'weight_ton' => 'decimal:3',
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

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function isLoaded(): bool
    {
        return $this->route_type === RouteType::Loaded;
    }

    public function isHalfLoaded(): bool
    {
        return $this->route_type === RouteType::HalfLoaded;
    }

    public function isRevenueLeg(): bool
    {
        return in_array($this->route_type, [RouteType::Loaded, RouteType::HalfLoaded], true);
    }
}
