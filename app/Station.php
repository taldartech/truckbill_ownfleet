<?php

namespace App;

use App\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'country_id',
        'state_id',
        'city_id',
        'name',
        'deleted_by',
        'deleted_reason'
    ];

    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'state_id' => 'integer',
            'city_id' => 'integer',
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

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
