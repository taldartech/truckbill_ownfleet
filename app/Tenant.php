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
            'payroll_pay_days_mode' => PayrollPayDaysMode::class,
        ];
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\TenantSubscription::class, 'tenant_id');
    }

    public function isPlanActive(): bool
    {
        return $this->subscription?->isActive() ?? false;
    }

    public function subscriptionAccessPayload(): array
    {
        return app(\App\Services\SubscriptionAccessService::class)->accessPayloadForTenant($this);
    }

    public function subscriptionCanWrite(): bool
    {
        return $this->subscriptionAccessPayload()['can_write'] ?? false;
    }

    public function subscriptionCanExport(): bool
    {
        return $this->subscriptionAccessPayload()['can_export'] ?? false;
    }

    public function subscriptionCanPrint(): bool
    {
        return $this->subscriptionAccessPayload()['can_print'] ?? false;
    }

    public function pendingUpcomingRenewals()
    {
        return $this->hasMany(\App\Models\UpcomingSubscription::class, 'tenant_id')->where('status', 'pending');
    }

    public function hasPendingUpcomingRenewal(): bool
    {
        return \App\Models\UpcomingSubscription::query()
            ->where('tenant_id', $this->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function planEndsOn(): ?\Illuminate\Support\Carbon
    {
        return $this->subscription?->ends_on;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
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
