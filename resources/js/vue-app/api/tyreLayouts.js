import api from '../api';

const base = '/app/api/tyre-layouts';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listTyreLayouts({ page = 1, perPage = 15, search, activeOnly = false } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(activeOnly ? { active_only: 1 } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listAllTyreLayouts({ activeOnly = true } = {}) {
    const { data } = await api.get(base, {
        params: {
            per_page: 500,
            ...(activeOnly ? { active_only: 1 } : {}),
        },
    });

    return data.data ?? [];
}

export async function seedDefaultTyreLayouts() {
    const { data } = await api.post(`${base}/seed-defaults`);
    return data.data ?? [];
}

export async function createTyreLayout(payload) {
    const { data } = await api.post(base, payload);
    return unwrapResource(data);
}

export async function updateTyreLayout(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);
    return unwrapResource(data);
}

export async function deleteTyreLayout(id, payload = {}) {
    const dataPayload = typeof payload === 'string'
        ? { deleted_reason: payload, remark: payload }
        : { deleted_reason: payload?.deleted_reason ?? payload?.remark, remark: payload?.remark ?? payload?.deleted_reason };

    const { data } = await api.delete(`${base}/${id}`, {
        data: dataPayload,
    });

    return data ?? null;
}

export async function fetchTyreLayout(id) {
    const { data } = await api.get(`${base}/${id}`);
    return unwrapResource(data);
}


export function parseAxleList(value) {
    if (Array.isArray(value)) {
        return value.map(Number).filter((n) => n > 0);
    }

    return String(value ?? '')
        .split(/[,\s]+/)
        .map((part) => Number(part.trim()))
        .filter((n) => Number.isFinite(n) && n > 0);
}

export function formatAxleList(axles) {
    return (axles ?? []).join(', ');
}

export function computePositionCount(layout) {
    if (layout?.layout_type === 'legacy_dual_rear') {
        return 7;
    }

    const steer = (layout?.steer_axles ?? 0) * 2;
    const dual = (layout?.dual_axles?.length ?? 0) * 4;
    const single = (layout?.single_axles?.length ?? 0) * 2;
    const spare = layout?.spare_count ?? 0;

    return steer + dual + single + spare;
}
