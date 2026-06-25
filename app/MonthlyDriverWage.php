<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\WagePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyDriverWage extends Model
{
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'driver_id',
        'month',
        'calendar_days',
        'total_days',
        'payable_days',
        'present_days',
        'absent_days',
        'leave_days',
        'substitute_days',
        'substitute_pay',
        'substitute_liability_days',
        'substitute_liability_pay',
        'total_wage',
        'gross_earning',
        'bonus',
        'absent_deduction',
        'base_salary',
        'hra',
        'holiday_allowance',
        'pf',
        'esi',
        'other_deduction',
        'advance_deducted',
        'diesel_deduction',
        'total_advance',
        'net_salary',
        'round_net',
        'payment_status',
        'paid_at',
        'payment_notes',
        'remarks',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'total_wage' => 'decimal:2',
            'gross_earning' => 'decimal:2',
            'bonus' => 'decimal:2',
            'absent_deduction' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'hra' => 'decimal:2',
            'holiday_allowance' => 'decimal:2',
            'pf' => 'decimal:2',
            'esi' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'advance_deducted' => 'decimal:2',
            'diesel_deduction' => 'decimal:2',
            'total_advance' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'round_net' => 'boolean',
            'substitute_pay' => 'decimal:2',
            'substitute_liability_pay' => 'decimal:2',
            'payment_status' => WagePaymentStatus::class,
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
