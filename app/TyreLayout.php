<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TyreLayout extends Model
{
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_LEGACY_DUAL_REAR = 'legacy_dual_rear';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'layout_type',
        'steer_axles',
        'dual_axles',
        'single_axles',
        'spare_count',
        'sort_order',
        'is_active',
        'deleted_by',
        'deleted_reason'
    ];

    protected function casts(): array
    {
        return [
            'steer_axles' => 'integer',
            'dual_axles' => 'array',
            'single_axles' => 'array',
            'spare_count' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isLegacyDualRear(): bool
    {
        return $this->layout_type === self::TYPE_LEGACY_DUAL_REAR;
    }

    public function positionSlotCount(): int
    {
        if ($this->isLegacyDualRear()) {
            return 7;
        }

        $dual = count($this->dual_axles ?? []) * 4;
        $single = count($this->single_axles ?? []) * 2;
        $steer = ($this->steer_axles ?? 0) * 2;

        return $steer + $dual + $single + ($this->spare_count ?? 0);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
