import api from '../api';

const base = '/app/api/maintenance-service-types';

export async function listMaintenanceServiceTypes({ perPage = 500 } = {}) {
    const { data } = await api.get(base, {
        params: { per_page: perPage },
    });

    return data.data ?? [];
}

export function buildMaintenanceServiceTypeSelectOptions(types) {
    return [...(types ?? [])]
        .sort((left, right) => {
            const leftActive = left.is_active !== false;
            const rightActive = right.is_active !== false;

            if (leftActive !== rightActive) {
                return leftActive ? -1 : 1;
            }

            return String(left.label ?? left.name ?? '').localeCompare(String(right.label ?? right.name ?? ''));
        })
        .map((type) => {
            const label = type.label ?? type.name ?? '—';
            const inactive = type.is_active === false;

            return {
                id: type.id,
                label,
                inactive,
                badge: inactive ? '(Inactive)' : '',
                searchText: [label, type.code].filter(Boolean).join(' '),
            };
        });
}
