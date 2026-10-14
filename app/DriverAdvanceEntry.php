<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\DriverAdvanceEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAdvanceEntry extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'driver_id',
        'entry_type',
        'amount',
        'entry_date',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => DriverAdvanceEntryType::class,
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function balanceFor(Driver $driver): float
    {
        $totals = self::query()
            ->where('driver_id', $driver->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as dr_total,
                 COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as cr_total',
                [DriverAdvanceEntryType::Dr->value, DriverAdvanceEntryType::Cr->value],
            )
            ->first();

        $dr = (float) ($totals->dr_total ?? 0);
        $cr = (float) ($totals->cr_total ?? 0);

        return round($dr - $cr, 2);
    }
}
