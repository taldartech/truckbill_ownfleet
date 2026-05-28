<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
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
        'wage_owner_driver_id',
        'attendance_date',
        'status',
        'wage_amount',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'wage_amount' => 'decimal:2',
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
}
