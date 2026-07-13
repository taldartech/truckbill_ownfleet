<?php

namespace App;

use App\Enums\GstRegistrationType;
use App\Enums\TaxLiability;
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
        'inherit_company_gst',
        'registration_type',
        'tax_liability',
        'gst_number',
        'state_id',
        'gst_rate',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'inherit_company_gst' => true,
    ];

    protected function casts(): array
    {
        return [
            'head_office' => 'boolean',
            'trip_sequence_start' => 'integer',
            'inherit_company_gst' => 'boolean',
            'state_id' => 'integer',
            'gst_rate' => 'decimal:3',
            'registration_type' => GstRegistrationType::class,
            'tax_liability' => TaxLiability::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'company_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
