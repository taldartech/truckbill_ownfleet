<?php

namespace App;

use App\Concerns\UsesUuid;
use App\Enums\GstRegistrationType;
use App\Enums\PayrollPayDaysMode;
use App\Enums\TaxLiability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        'company_logo',
        'document_color',
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
            'state_id' => 'integer',
            'gst_rate' => 'decimal:3',
            'registration_type' => GstRegistrationType::class,
            'tax_liability' => TaxLiability::class,
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

    public function getPlanEndDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->planEndsOn();
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

    public function storeLogo(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $path = sprintf('logos/companies/%s/%s.%s', $this->id, (string) Str::uuid(), $extension);

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public function deleteLogo(): void
    {
        if ($this->company_logo) {
            Storage::disk('public')->delete($this->company_logo);
            $this->company_logo = null;
        }
    }

    public function logoPublicUrl(): ?string
    {
        return $this->company_logo
            ? Storage::disk('public')->url($this->company_logo)
            : null;
    }

    public function logoFilesystemPathForPdf(): ?string
    {
        if (! $this->company_logo || ! Storage::disk('public')->exists($this->company_logo)) {
            return null;
        }

        return Storage::disk('public')->path($this->company_logo);
    }

    public function documentColor(): ?string
    {
        $color = strtoupper(trim((string) ($this->document_color ?? '')));

        if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            return null;
        }

        return $color;
    }

    public function documentTextColor(): string
    {
        $color = $this->documentColor();

        if ($color === null) {
            return '#000000';
        }

        $red = hexdec(substr($color, 1, 2));
        $green = hexdec(substr($color, 3, 2));
        $blue = hexdec(substr($color, 5, 2));
        $luminance = ((0.299 * $red) + (0.587 * $green) + (0.114 * $blue)) / 255;

        return $luminance > 0.6 ? '#000000' : '#FFFFFF';
    }

    /**
     * Solid hex tint equivalent to the document color at the given opacity over white.
     * DomPDF does not reliably honor rgba()/opacity, so PDFs use a blended hex instead.
     */
    public function documentColorWithOpacity(float $opacity = 0.2): ?string
    {
        $color = $this->documentColor();

        if ($color === null) {
            return null;
        }

        $opacity = max(0.0, min(1.0, $opacity));
        $whiteShare = 1 - $opacity;

        $red = (int) round((hexdec(substr($color, 1, 2)) * $opacity) + (255 * $whiteShare));
        $green = (int) round((hexdec(substr($color, 3, 2)) * $opacity) + (255 * $whiteShare));
        $blue = (int) round((hexdec(substr($color, 5, 2)) * $opacity) + (255 * $whiteShare));

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }
}
