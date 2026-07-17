<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSetting extends Model
{
    public const YEAR_TYPE_BLANK = 'blank';

    public const YEAR_TYPE_YEAR = 'year';

    public const YEAR_TYPE_FINANCIAL_YEAR = 'financial_year';

    public const YEAR_TYPES = [
        self::YEAR_TYPE_BLANK,
        self::YEAR_TYPE_YEAR,
        self::YEAR_TYPE_FINANCIAL_YEAR,
    ];

    protected $fillable = [
        'branch_id',
        'trip_initial',
        'trip_separator_1',
        'trip_year_type',
        'trip_year_digits',
        'trip_separator_2',
        'trip_sequence_digits',
        'invoice_initial',
        'invoice_separator_1',
        'invoice_year_type',
        'invoice_year_digits',
        'invoice_separator_2',
        'invoice_sequence_digits',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'trip_year_digits' => 'integer',
            'trip_sequence_digits' => 'integer',
            'invoice_year_digits' => 'integer',
            'invoice_sequence_digits' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsForBranch(int $branchId): array
    {
        $branchCode = sprintf('BR%03d', $branchId);

        return [
            'branch_id' => $branchId,
            'trip_initial' => 'TRIP-'.$branchCode,
            'trip_separator_1' => '-',
            'trip_year_type' => self::YEAR_TYPE_YEAR,
            'trip_year_digits' => 4,
            'trip_separator_2' => '-',
            'trip_sequence_digits' => 4,
            'invoice_initial' => 'INV-'.$branchCode,
            'invoice_separator_1' => '-',
            'invoice_year_type' => self::YEAR_TYPE_YEAR,
            'invoice_year_digits' => 4,
            'invoice_separator_2' => '-',
            'invoice_sequence_digits' => 4,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createForBranch(int $branchId, array $attributes = []): self
    {
        return self::query()->create([
            ...self::defaultsForBranch($branchId),
            ...$attributes,
            'branch_id' => $branchId,
        ]);
    }
}
