import { computed, inject, unref } from 'vue';

/**
 * Single source for UI permission checks (mirrors backend permission slugs on user.permissions).
 */
export function usePermissions() {
    const currentUser = inject('currentUser', null);
    const user = computed(() => unref(currentUser) ?? window.__AUTH_USER__ ?? null);

    const permissions = computed(() => user.value?.permissions ?? []);

    function can(permission) {
        return permissions.value.includes(permission);
    }

    function canAny(list) {
        return list.some((permission) => can(permission));
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

    return {
        user,
        permissions,
        can,
        canAny,
        canInCompany,
    };
}
