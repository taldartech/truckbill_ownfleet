<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\AttendanceMonthStatus;
use Illuminate\Database\Eloquent\Model;

class AttendanceMonthLock extends Model
{
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'month',
        'status',
        'closed_at',
        'locked_at',
        'closed_by',
        'locked_by',
        'vehicle_settlement',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'status' => AttendanceMonthStatus::class,
            'closed_at' => 'datetime',
            'locked_at' => 'datetime',
            'vehicle_settlement' => 'array',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === AttendanceMonthStatus::Open;
    }
}
