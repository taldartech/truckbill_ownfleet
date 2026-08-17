import api from '../api';

const base = '/app/api/monthly-expenses';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listMonthlyExpenses({ page = 1, perPage = 25, search = '', month = '' } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(month ? { month } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function createMonthlyExpense(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateMonthlyExpense(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteMonthlyExpense(id) {
    await api.delete(`${base}/${id}`);
}
