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

export function useFilteredNavigation(items) {
    const { can, canInCompany, user } = usePermissions();

    return computed(() =>
        filterNavItems(
            items,
            can,
            canInCompany,
            Boolean(user.value?.requires_company_selection),
        ),
    );
}
