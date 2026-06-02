<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'registration_number',
        'vehicle_type',
        'make',
        'model',
        'manufacture_year',
        'odometer_km',
        'created_by',
        'updated_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function currentPrimaryAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class)
            ->where('is_primary', true)
            ->whereNull('assigned_to')
            ->latest('assigned_from');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }
}
