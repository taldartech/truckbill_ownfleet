<?php

namespace App;

use App\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'country_id',
        'state_id',
        'city_id',
        'agency_id',
        'name',
        'gst_number',
        'pan_number',
        'address',
        'contact_number',
        'is_broker',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_reason',
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'state_id' => 'integer',
            'city_id' => 'integer',
            'agency_id' => 'integer',
            'is_broker' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
