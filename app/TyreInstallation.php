<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TyrePosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreInstallation extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'tyre_id',
        'vehicle_id',
        'position_code',
        'installed_at',
        'removed_at',
        'km_at_installation',
        'km_at_removal',
        'running_km',
        'removal_recommendation',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'removed_at' => 'date',
            'km_at_installation' => 'decimal:2',
            'km_at_removal' => 'decimal:2',
            'running_km' => 'decimal:2',
            'position_code' => TyrePosition::class,
        ];
    }

    public function tyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
