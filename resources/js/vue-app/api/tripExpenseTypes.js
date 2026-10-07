import api from '../api';

const base = '/app/api/trip-expense-types';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listTripExpenseTypes({ page = 1, perPage = 15, search = '', activeOnly = false } = {}) {
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

export async function fetchActiveTripExpenseTypes() {
    const { data } = await api.get(base, {
        params: { all: 1, active_only: 1 },
    });

    return (data.data ?? []).map((t) => ({
        value: t.code,
        label: t.name,
        is_active: t.is_active,
    }));
}

export async function createTripExpenseType(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateTripExpenseType(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteTripExpenseType(id) {
    const { data } = await api.delete(`${base}/${id}`);

    return data;
}
