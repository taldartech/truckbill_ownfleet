<?php

namespace App;

use App\Concerns\HasPermissions;
use App\Concerns\UsesUuid;
use App\Support\SelectedCompanySession;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasPermissions;
    use Notifiable;
    use UsesUuid;

    private ?string $cachedEffectiveTenantId = null;

    private bool $effectiveTenantIdResolved = false;

    /**
     * @var array<string, string>
     */
    private const ROLE_ALIASES = [
        'fleet_manager' => 'admin',
        'driver' => 'customer',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'user_role_id',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_role_id' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Legacy Blade layouts expect a company(); tenant is the company scope in own-fleet.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function resolvedCompany(): ?Tenant
    {
        if ($this->tenant_id !== null) {
            return $this->relationLoaded('tenant') ? $this->tenant : $this->tenant()->first();
        }

        $tenantId = $this->effectiveTenantId();

        return $tenantId !== null ? Tenant::query()->find($tenantId) : null;
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_user_id');
    }

    public function userRole(): BelongsTo
    {
        return $this->belongsTo(UserRole::class);
    }

    /**
     * Effective tenant/company scope: fixed tenant_id for staff; session for super_admin.
     */
    public function effectiveTenantId(): ?string
    {
        $raw = $this->attributes['tenant_id'] ?? null;

        if ($raw !== null) {
            if (! $this->effectiveTenantIdResolved) {
                $this->cachedEffectiveTenantId = (string) $raw;
                $this->effectiveTenantIdResolved = true;
            }

            return $this->cachedEffectiveTenantId;
        }

        if ($this->hasRole('super_admin')) {
            return SelectedCompanySession::id();
        }

        return null;
    }

    /**
     * Alias for legacy company_id usage (same UUID as tenant_id).
     */
    public function getCompanyIdAttribute(): ?string
    {
        return $this->effectiveTenantId();
    }

    public function hasRole(string $role): bool
    {
        return $this->matchesRole($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->matchesRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function canActAsAdmin(): bool
    {
        return $this->hasAnyRole([
            'admin',
            'super_admin',
            'master_admin',
            'FLEET_MANAGER',
            'ADMIN',
            'SUPER_ADMIN',
        ]);
    }

    public function dashboardUrl(): string
    {
        if ($this->hasRole('super_admin') && $this->effectiveTenantId() === null) {
            return '/app/companies';
        }

        return '/app/dashboard';
    }

    public function canAccessCompanyPanel(): bool
    {
        if (in_array($this->user_role_id, [
            UserRole::ADMIN,
            UserRole::MODERATOR,
            UserRole::ACCOUNTANT,
        ], true)) {
            return true;
        }

        return $this->user_role_id === UserRole::SUPER_ADMIN && $this->effectiveTenantId() !== null;
    }

    public function requiresCompanySelection(): bool
    {
        return $this->user_role_id === UserRole::SUPER_ADMIN && $this->effectiveTenantId() === null;
    }

    private function matchesRole(string $role): bool
    {
        $current = $this->userRole?->name;

        if ($current === null) {
            return false;
        }

        $normalized = self::normalizeRoleName($role);
        $expected = self::ROLE_ALIASES[$normalized] ?? $normalized;

        return $current === $expected;
    }

    private static function normalizeRoleName(string $role): string
    {
        return strtolower(str_replace('-', '_', $role));
    }
}
