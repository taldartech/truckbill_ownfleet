<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class VehicleDocument extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'document_type',
        'issue_date',
        'expiry_date',
        'file_url',
        'reminder_days_before',
        'created_by',
        'updated_by',
    ];
}
