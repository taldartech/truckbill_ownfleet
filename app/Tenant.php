<?php

namespace App;

use App\Concerns\UsesUuid;
use App\Enums\PayrollPayDaysMode;
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
        'gst_scheme',
        'gst_type',
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
            'payroll_pay_days_mode' => PayrollPayDaysMode::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
