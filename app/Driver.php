<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'phone',
        'license_number',
        'license_expiry_date',
        'address',
        'aadhaar_number',
        'pan_number',
        'bank_account_number',
        'bank_ifsc',
        'joining_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'daily_wage',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_reason'
    ];

    protected function casts(): array
    {
        return [
            'license_expiry_date' => 'date',
            'joining_date' => 'date',
            'daily_wage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function primaryAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'primary_driver_id');
    }

    public function advanceEntries(): HasMany
    {
        return $this->hasMany(DriverAdvanceEntry::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function currentPrimaryAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DriverAssignment::class)
            ->where('is_primary', true)
            ->whereDate('assigned_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>', now()->toDateString());
            });
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }
}
