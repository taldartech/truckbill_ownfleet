<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\PickupDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripPickup extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'trip_id',
        'pickup_code',
        'sequence_no',
        'station_id',
        'consignor',
        'from_address',
        'goods_name',
        'no_of_units',
        'goods_value',
        'ewaybill_no',
        'ewaybill_expiry',
        'pickup_date',
        'weight_ton',
    ];

    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'station_id' => 'integer',
            'no_of_units' => 'integer',
            'goods_value' => 'decimal:2',
            'ewaybill_expiry' => 'date',
            'pickup_date' => 'date',
            'weight_ton' => 'decimal:3',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function drops(): HasMany
    {
        return $this->hasMany(TripRouteLeg::class, 'trip_pickup_id')->orderBy('sequence_no');
    }

    public function deliveredWeightTon(): float
    {
        if ($this->relationLoaded('drops')) {
            return round((float) $this->drops->sum(fn (TripRouteLeg $leg): float => (float) ($leg->weight_ton ?? 0)), 3);
        }

        return round((float) $this->drops()->sum('weight_ton'), 3);
    }

    public function remainingWeightTon(): float
    {
        return round(max(0, (float) $this->weight_ton - $this->deliveredWeightTon()), 3);
    }

    public function deliveryStatus(): PickupDeliveryStatus
    {
        $pickupWeight = round((float) $this->weight_ton, 3);
        $delivered = $this->deliveredWeightTon();

        if ($delivered <= 0.0005) {
            return PickupDeliveryStatus::Pending;
        }

        if ($delivered + 0.0005 >= $pickupWeight) {
            return PickupDeliveryStatus::Delivered;
        }

        return PickupDeliveryStatus::Partial;
    }
}
