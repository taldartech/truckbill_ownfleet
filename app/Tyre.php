<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TyreStatus;
use App\Enums\TyreType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tyre extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'serial_number',
        'brand',
        'size',
        'purchase_date',
        'purchase_cost',
        'warranty_details',
        'warranty_valid_until',
        'warranty_km',
        'tyre_type',
        'current_km',
        'expected_life_km',
        'status',
        'scrapped_at',
        'scrap_notes',
        'scrap_sale_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_valid_until' => 'date',
            'warranty_km' => 'decimal:2',
            'purchase_cost' => 'decimal:2',
            'current_km' => 'decimal:2',
            'expected_life_km' => 'decimal:2',
            'status' => TyreStatus::class,
            'tyre_type' => TyreType::class,
            'scrapped_at' => 'date',
            'scrap_sale_amount' => 'decimal:2',
        ];
    }

    public function installations(): HasMany
    {
        return $this->hasMany(TyreInstallation::class);
    }

    public function activeInstallation(): HasOne
    {
        return $this->hasOne(TyreInstallation::class)->whereNull('removed_at')->latest('installed_at');
    }

    public function rotations(): HasMany
    {
        return $this->hasMany(TyreRotation::class);
    }

    public function retreads(): HasMany
    {
        return $this->hasMany(TyreRetread::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(TyreDamage::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TyreExpense::class);
    }
}
