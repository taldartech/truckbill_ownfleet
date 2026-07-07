import { computed, inject, unref } from 'vue';
import { userHasAnyPermission, userHasPermission } from '../utils/permissionAlias';

/**
 * Single source for UI permission checks (mirrors backend permission slugs on user.permissions).
 *
 * @param {import('vue').MaybeRefOrGetter<object|null|undefined>} [explicitUserRef] Pass from AppShellLayout so nav reacts when company context changes (inject is unavailable in the same component as provide).
 */
export function usePermissions(explicitUserRef = null) {
    const injectedUser = inject('currentUser', null);
    const user = computed(() => {
        if (explicitUserRef != null) {
            return unref(explicitUserRef);
        }

        return unref(injectedUser) ?? window.__AUTH_USER__ ?? null;
    });

    const permissions = computed(() => user.value?.permissions ?? []);

    function can(permission) {
        return userHasPermission(permissions.value, permission);
    }

    function canAny(list) {
        return userHasAnyPermission(permissions.value, list);
    }

    /** Permission plus active company context (super_admin after Enter). */
    function canInCompany(permission) {
        if (!can(permission)) {
            return false;
        }

        if (user.value?.requires_company_selection) {
            return false;
        }

        const tenantId = user.value?.effective_tenant_id ?? user.value?.company_id;

        return tenantId != null;
    }

    function canAnyInCompany(slugs) {
        return slugs.some((slug) => canInCompany(slug));
    }

    function canCreateInCompany(module) {
        return canAnyInCompany([`${module}.create`, `${module}.manage`]);
    }

    function canEditInCompany(module) {
        return canAnyInCompany([`${module}.edit`, `${module}.manage`]);
    }

    const canDeleteRecords = computed(() => user.value?.can_delete_records === true);

    return {
        user,
        permissions,
        can,
        canAny,
        canInCompany,
        canAnyInCompany,
        canCreateInCompany,
        canEditInCompany,
        canDeleteRecords,
    };
}
