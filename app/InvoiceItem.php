<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'invoice_id',
        'trip_route_leg_id',
        'sequence_no',
        'consignor',
        'consignee',
        'from_address',
        'to_address',
        'from_location',
        'to_location',
        'goods_name',
        'weight_ton',
        'freight_amount',
        'route_legs_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'weight_ton' => 'decimal:3',
            'freight_amount' => 'decimal:2',
            'route_legs_snapshot' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function routeLeg(): BelongsTo
    {
        return $this->belongsTo(TripRouteLeg::class, 'trip_route_leg_id');
    }
}
