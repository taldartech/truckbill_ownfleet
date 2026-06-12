<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\AttendanceMarkedVia;
use App\Enums\WagePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'primary_driver_id',
        'temporary_driver_id',
        'temporary_assignment_remarks',
        'wage_owner_driver_id',
        'attendance_date',
        'status',
        'marked_via',
        'wage_amount',
        'substitute_payment_amount',
        'wage_payment_status',
        'wage_paid_at',
        'wage_transfer_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'wage_amount' => 'decimal:2',
            'substitute_payment_amount' => 'decimal:2',
            'marked_via' => AttendanceMarkedVia::class,
            'wage_payment_status' => WagePaymentStatus::class,
            'wage_paid_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function primaryDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'primary_driver_id');
    }

    public function temporaryDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'temporary_driver_id');
    }

    public function wageOwnerDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'wage_owner_driver_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isWagePaid(): bool
    {
        return $this->wage_payment_status === WagePaymentStatus::Paid;
    }
}
