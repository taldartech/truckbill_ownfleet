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

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
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
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hasRole(string $role): bool
    {
        return strtoupper((string) $this->role) === strtoupper($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        $normalized = array_map(static fn (string $role): string => strtoupper($role), $roles);

        return in_array(strtoupper((string) $this->role), $normalized, true);
    }

    public function canActAsAdmin(): bool
    {
        return $this->hasAnyRole(['ADMIN', 'SUPER_ADMIN', 'FLEET_MANAGER']);
    }
}
