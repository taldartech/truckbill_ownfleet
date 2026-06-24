<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected function casts(): array
    {
        return [
            'head_office' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'company_id');
    }
}
