<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;
    use TenantScoped;
    use UsesUuid;

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

    public function userRole(): BelongsTo
    {
        return $this->belongsTo(UserRole::class);
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

        return $this->userRole?->name === 'super_admin'
            && session('super_admin_company_id') !== null;
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
