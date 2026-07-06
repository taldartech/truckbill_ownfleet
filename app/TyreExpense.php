<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TyreExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreExpense extends Model
{
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'tyre_id',
        'expense_type',
        'amount',
        'expense_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_type' => TyreExpenseType::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function tyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class);
    }
}
