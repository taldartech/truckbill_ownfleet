<?php

namespace App\Support;

use App\Tenant;
use App\User;
use App\UserRole;

class RoleHierarchy
{
    /**
     * @var array<int, string>
     */
    private const ROLE_LABELS = [
        UserRole::MASTER_ADMIN => 'Master Admin',
        UserRole::SUPER_ADMIN => 'Super Admin',
        UserRole::ADMIN => 'Admin',
        UserRole::MODERATOR => 'Moderator',
        UserRole::ACCOUNTANT => 'Accountant',
    ];

    /**
     * Roles a user manager may assign when creating/editing users.
     *
     * @return list<int>
     */
    public static function assignableRoleIds(User $actor): array
    {
        return match ($actor->user_role_id) {
            UserRole::MASTER_ADMIN => [],
            UserRole::SUPER_ADMIN => [
                UserRole::ADMIN,
                UserRole::MODERATOR,
                UserRole::ACCOUNTANT,
            ],
            default => [],
        };
    }

    public static function canAssignRole(User $actor, int $roleId): bool
    {
        return in_array($roleId, self::assignableRoleIds($actor), true);
    }

    public static function isProtectedRole(int $roleId): bool
    {
        return $roleId === UserRole::MASTER_ADMIN;
    }

    public static function isSuperAdminRole(int $roleId): bool
    {
        return $roleId === UserRole::SUPER_ADMIN;
    }

    public static function managesSuperAdmins(User $actor): bool
    {
        return $actor->user_role_id === UserRole::MASTER_ADMIN;
    }

    public static function managesTenantUsers(User $actor): bool
    {
        return $actor->user_role_id === UserRole::SUPER_ADMIN;
    }

    public static function requiresBranchAssignment(int $roleId): bool
    {
        return in_array($roleId, [
            UserRole::ADMIN,
            UserRole::MODERATOR,
            UserRole::ACCOUNTANT,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function ownedTenantIds(User $actor): array
    {
        if ($actor->user_role_id !== UserRole::SUPER_ADMIN) {
            return [];
        }

        return Tenant::query()
            ->where('owner_user_id', $actor->id)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    public static function actorOwnsTenant(User $actor, ?string $tenantId): bool
    {
        if ($tenantId === null || $tenantId === '') {
            return false;
        }

        return in_array($tenantId, self::ownedTenantIds($actor), true);
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if (self::isProtectedRole((int) $target->user_role_id)) {
            return false;
        }

        if ($actor->user_role_id === UserRole::MASTER_ADMIN) {
            return self::isSuperAdminRole((int) $target->user_role_id);
        }

        if ($actor->user_role_id === UserRole::SUPER_ADMIN) {
            if (self::isSuperAdminRole((int) $target->user_role_id)) {
                return false;
            }

            return $target->tenant_id !== null
                && self::actorOwnsTenant($actor, (string) $target->tenant_id);
        }

        return false;
    }

    public static function canDeleteUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return self::canManageUser($actor, $target);
    }

    public static function canModifyRolePermissions(User $actor, UserRole $role): bool
    {
        if ($role->id === UserRole::MASTER_ADMIN) {
            return $actor->user_role_id === UserRole::MASTER_ADMIN;
        }

        return $actor->user_role_id === UserRole::MASTER_ADMIN;
    }

    /**
     * @return list<string>
     */
    public static function roleLabel(int $roleId): string
    {
        return self::ROLE_LABELS[$roleId] ?? 'Unknown';
    }
}
