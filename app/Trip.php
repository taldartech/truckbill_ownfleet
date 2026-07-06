<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
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
        'half_loaded_distance_km',
        'empty_distance_km',
        'total_expense_amount',
        'total_freight_amount',
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
            'half_loaded_distance_km' => 'decimal:2',
            'empty_distance_km' => 'decimal:2',
            'total_expense_amount' => 'decimal:2',
            'total_freight_amount' => 'decimal:2',
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
        return (float) $this->total_freight_amount - (float) $this->total_expense_amount;
    }
}
