import api from '../api';

const base = '/app/api/maintenance-service-types';

export async function listMaintenanceServiceTypes({ perPage = 500 } = {}) {
    const { data } = await api.get(base, {
        params: { per_page: perPage },
    });

    return data.data ?? [];
}

export function buildMaintenanceServiceTypeSelectOptions(types) {
    return (types ?? []).map((type) => ({
        id: type.id,
        value: String(type.id),
        label: type.label ?? type.name,
        subtitle: type.code,
    }));
}
