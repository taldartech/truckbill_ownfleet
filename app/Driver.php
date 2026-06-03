<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'employee_code',
        'father_name',
        'phone',
        'license_number',
        'license_expiry_date',
        'address',
        'aadhaar_number',
        'pan_number',
        'uan_number',
        'esic_number',
        'bank_account_number',
        'bank_ifsc',
        'joining_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'daily_wage',
        'is_active',
        'created_by',
        'updated_by',
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
}
