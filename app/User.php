<?php

namespace App;

use App\Concerns\HasPermissions;
use App\Concerns\UsesUuid;
use App\Mail\ResetPasswordMail;
use App\Support\RoleHierarchy;
use App\Support\SelectedBranchSession;
use App\Support\SelectedCompanySession;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasPermissions;
    use Notifiable;
    use UsesUuid;
    use SoftDeletes;

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new \App\Scopes\SafeSoftDeletingScope);
    }

    private ?string $cachedEffectiveTenantId = null;

    private bool $effectiveTenantIdResolved = false;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'email',
        'password',
        'email_verified_at',
        'user_role_id',
        'document_alerts_live',
        'document_alerts_email',
        'policy_accepted_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'password' => 'hashed',
            'user_role_id' => 'integer',
            'branch_id' => 'integer',
            'document_alerts_live' => 'boolean',
            'document_alerts_email' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deleted_by');
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

    public function resolvedSuperAdminId(): ?string
    {
        if ($this->user_role_id === UserRole::SUPER_ADMIN) {
            return (string) $this->id;
        }

        $tenant = $this->resolvedCompany();
        if ($tenant?->owner_user_id) {
            return (string) $tenant->owner_user_id;
        }

        if ($this->tenant_id) {
            $ownerId = Tenant::query()->where('id', $this->tenant_id)->value('owner_user_id');
            if ($ownerId) {
                return (string) $ownerId;
            }
        }

        if ($this->hasRole('super_admin')) {
            return (string) $this->id;
        }

        return null;
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
            'master_admin',
            'super_admin',
            'admin',
            'moderator',
        ]);
    }

    public function dashboardUrl(): string
    {
        return '/app/dashboard';
    }

    public function sendPasswordResetNotification($token): void
    {
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        Mail::to($this->email)->send(new ResetPasswordMail(
            email: $this->email,
            token: $token,
            count: $expireMinutes,
        ));
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

        return $this->user_role_id === UserRole::SUPER_ADMIN && $this->effectiveBranchId() !== null;
    }

    public function effectiveBranchId(): ?int
    {
        if ($this->requiresAssignedBranch()) {
            return $this->branch_id !== null ? (int) $this->branch_id : null;
        }

        if ($this->hasRole('super_admin')) {
            return SelectedBranchSession::id();
        }

        return null;
    }

    public function requiresBranchSelection(): bool
    {
        return $this->hasRole('super_admin') && $this->effectiveBranchId() === null;
    }

    public function requiresCompanySelection(): bool
    {
        return $this->requiresBranchSelection();
    }

    public function requiresAssignedBranch(): bool
    {
        return RoleHierarchy::requiresBranchAssignment((int) $this->user_role_id);
    }

    public function canLoginWithCurrentBranchAssignment(): bool
    {
        if (! $this->requiresAssignedBranch()) {
            return true;
        }

        return $this->branch_id !== null;
    }

    public function canDeleteOperationalRecords(): bool
    {
        return ! $this->hasAnyRole(['accountant', 'moderator']);
    }

    public function canUpdateOperationalRecords(): bool
    {
        return ! $this->hasAnyRole(['accountant', 'moderator']);
    }

    private function matchesRole(string $role): bool
    {
        $current = $this->userRole?->name;

        if ($current === null) {
            return false;
        }

        return $current === self::normalizeRoleName($role);
    }

    private static function normalizeRoleName(string $role): string
    {
        $normalized = strtolower(str_replace('-', '_', $role));

        if ($normalized === 'operator') {
            return 'moderator';
        }

        return $normalized;
    }
}
