import { computed } from 'vue';
import { usePermissions } from './usePermissions';

function filterNavItems(items, can, canInCompany, requiresCompanySelection) {
    return items
        .map((item) => {
            if (item.children) {
                const children = filterNavItems(
                    item.children,
                    can,
                    canInCompany,
                    requiresCompanySelection,
                );

                if (children.length === 0) {
                    return null;
                }

                return { ...item, children };
            }

            if (item.requiresTenant && requiresCompanySelection) {
                return null;
            }

            if (item.permission) {
                const check = item.requiresTenant ? canInCompany : can;

                if (!check(item.permission)) {
                    return null;
                }
            }

            return item;
        })
        .filter(Boolean);
}

/**
 * @param {import('vue').MaybeRefOrGetter<object|null|undefined>} [userRef] Reactive user ref from AppShellLayout.
 */
export function useFilteredNavigation(items, userRef = null) {
    const { can, canInCompany, user } = usePermissions(userRef);

    return computed(() => {
        const current = user.value;

        // Track company context and permissions so the sidebar updates after Enter / Exit company.
        void current?.requires_company_selection;
        void current?.effective_tenant_id;
        void current?.company_id;
        void current?.permissions?.length;

        return filterNavItems(
            items,
            can,
            canInCompany,
            Boolean(current?.requires_company_selection),
        );
    });
}
