<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Support\VehicleRegistrationNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'registration_number',
        'vehicle_type',
        'tyre_layout',
        'make',
        'model',
        'manufacture_year',
        'odometer_km',
        'expected_mileage_km_per_liter',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expected_mileage_km_per_liter' => 'decimal:2',
        ];
    }

    public function setRegistrationNumberAttribute(?string $value): void
    {
        $this->attributes['registration_number'] = VehicleRegistrationNumber::normalize($value) ?? '';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function currentPrimaryAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class)
            ->tap(fn ($query) => static::applyCurrentPrimaryAssignmentConstraints($query))
            ->latest('assigned_from');
    }

    /**
     * @param  Builder<DriverAssignment>  $query
     */
    public static function applyCurrentPrimaryAssignmentConstraints(Builder $query, ?string $asOfDate = null): void
    {
        /** @var Builder<DriverAssignment> $query */
        $query->activePrimaryOn($asOfDate ?? now()->toDateString());
    }

    /**
     * @param  Builder<Vehicle>  $query
     */
    public function scopeFilterByPrimaryDriver(Builder $query, string $assignment): void
    {
        $constraint = static function (Builder $assignmentQuery): void {
            static::applyCurrentPrimaryAssignmentConstraints($assignmentQuery);
        };

        if ($assignment === 'assigned') {
            $query->whereHas('assignments', $constraint);
        } elseif ($assignment === 'unassigned') {
            $query->whereDoesntHave('assignments', $constraint);
        }
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }
}
