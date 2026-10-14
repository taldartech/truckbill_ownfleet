<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TripExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripExpense extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'trip_id',
        'trip_route_leg_id',
        'expense_type',
        'other_type',
        'expense_date',
        'amount',
        'fuel_liters',
        'fuel_rate',
        'fuel_odometer_km',
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
            'fuel_odometer_km' => 'decimal:2',
            'expense_date' => 'date',
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
