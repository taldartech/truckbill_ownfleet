<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\BatteryStatus;
use App\Enums\BatteryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Battery extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    public const WARRANTY_EXPIRING_DAYS = 30;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'serial_number',
        'brand',
        'model',
        'battery_type',
        'voltage',
        'capacity_ah',
        'purchase_date',
        'purchase_cost',
        'warranty_months',
        'warranty_valid_until',
        'expected_life_months',
        'supplier_name',
        'status',
        'scrapped_at',
        'scrap_sale_amount',
        'scrap_notes',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_valid_until' => 'date',
            'scrapped_at' => 'date',
            'purchase_cost' => 'decimal:2',
            'capacity_ah' => 'decimal:2',
            'scrap_sale_amount' => 'decimal:2',
            'warranty_months' => 'integer',
            'expected_life_months' => 'integer',
            'status' => BatteryStatus::class,
            'battery_type' => BatteryType::class,
        ];
    }

    public function installations(): HasMany
    {
        return $this->hasMany(BatteryInstallation::class);
    }

    public function activeInstallation(): HasOne
    {
        return $this->hasOne(BatteryInstallation::class)
            ->whereNull('removed_at')
            ->latest('installed_at');
    }

    public function replacementsAsOld(): HasMany
    {
        return $this->hasMany(BatteryReplacement::class, 'old_battery_id');
    }

    public function replacementsAsNew(): HasMany
    {
        return $this->hasMany(BatteryReplacement::class, 'new_battery_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(BatteryInspection::class)->latest('inspected_at');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', BatteryStatus::InStock->value);
    }

    public function scopeInstalled(Builder $query): Builder
    {
        return $query->where('status', BatteryStatus::Installed->value);
    }

    public function scopeUnderInspection(Builder $query): Builder
    {
        return $query->where('status', BatteryStatus::UnderInspection->value);
    }

    public function scopeWarrantyExpiring(Builder $query, int $days = self::WARRANTY_EXPIRING_DAYS): Builder
    {
        $today = now()->toDateString();
        $cutoff = now()->addDays($days)->toDateString();

        return $query->whereNotNull('warranty_valid_until')
            ->where('warranty_valid_until', '>=', $today)
            ->where('warranty_valid_until', '<=', $cutoff);
    }
}
