<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TripExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripExpense extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'trip_id',
        'trip_route_leg_id',
        'expense_type',
        'amount',
        'fuel_liters',
        'fuel_rate',
        'odometer_start',
        'odometer_end',
        'currency',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_type' => TripExpenseType::class,
            'amount' => 'decimal:2',
            'fuel_liters' => 'decimal:2',
            'fuel_rate' => 'decimal:2',
            'odometer_start' => 'decimal:2',
            'odometer_end' => 'decimal:2',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function routeLeg(): BelongsTo
    {
        return $this->belongsTo(TripRouteLeg::class, 'trip_route_leg_id');
    }
}
