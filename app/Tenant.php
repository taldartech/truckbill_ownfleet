<?php

namespace App;

use App\Concerns\UsesUuid;
use App\Enums\GstRegistrationType;
use App\Enums\PayrollPayDaysMode;
use App\Enums\TaxLiability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use UsesUuid;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'short_name',
        'timezone',
        'is_active',
        'is_blocked',
        'gst_number',
        'pan_number',
        'state_id',
        'gst_rate',
        'tax_liability',
        'registration_type',
        'address',
        'contact_person',
        'contact_number',
        'plan_end_date',
        'lr_credits',
        'truck_credits',
        'payroll_pay_days_mode',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payroll_pay_days_mode' => 'calendar',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_blocked' => 'boolean',
            'plan_end_date' => 'date',
            'lr_credits' => 'integer',
            'truck_credits' => 'integer',
            'state_id' => 'integer',
            'gst_rate' => 'decimal:3',
            'registration_type' => GstRegistrationType::class,
            'tax_liability' => TaxLiability::class,
            'payroll_pay_days_mode' => PayrollPayDaysMode::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    public static function ensureUniqueShortName(string $base): string
    {
        $candidate = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $base) ?: 'CO', 12, ''));
        $original = $candidate;
        $suffix = 1;

        while (static::query()->where('short_name', $candidate)->exists()) {
            $candidate = Str::limit($original, 10, '').$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public static function ensureUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $candidate = $slug;
        $suffix = 1;

        while (static::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
