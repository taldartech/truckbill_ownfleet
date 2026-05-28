<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class TripRouteLeg extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'trip_id',
        'from_location',
        'to_location',
        'route_type',
        'sequence_no',
        'distance_km',
    ];
}
