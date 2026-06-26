<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_name',
        'branch_address',
        'branch_contact_number',
        'head_office',
        'trip_sequence_start',
    ];

    protected function casts(): array
    {
        return [
            'head_office' => 'boolean',
            'trip_sequence_start' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'company_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
