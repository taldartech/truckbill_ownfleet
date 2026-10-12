<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripExpenseTypeMaster extends Model
{
    use SoftDeletes;

    protected $table = 'trip_expense_types';

    public const SYSTEM_CODES = ['DIESEL', 'OTHER'];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function isSystem(): bool
    {
        return in_array(strtoupper((string) $this->code), self::SYSTEM_CODES, true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
