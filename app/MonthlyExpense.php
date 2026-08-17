<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class MonthlyExpense extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'month',
        'particulars',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public static function sumForMonthRange(string|\DateTimeInterface $fromDate, string|\DateTimeInterface $toDate): float
    {
        return round((float) collect(static::itemsForMonthRange($fromDate, $toDate))->sum('amount'), 2);
    }

    /**
     * @return list<array{particulars: string, amount: float}>
     */
    public static function itemsForMonthRange(string|\DateTimeInterface $fromDate, string|\DateTimeInterface $toDate): array
    {
        $fromMonth = Carbon::parse($fromDate)->format('Y-m');
        $toMonth = Carbon::parse($toDate)->format('Y-m');

        return static::query()
            ->where('month', '>=', $fromMonth)
            ->where('month', '<=', $toMonth)
            ->orderBy('month')
            ->orderBy('particulars')
            ->orderBy('id')
            ->get(['particulars', 'amount'])
            ->map(fn (self $expense): array => [
                'particulars' => (string) $expense->particulars,
                'amount' => round((float) $expense->amount, 2),
            ])
            ->all();
    }
}
