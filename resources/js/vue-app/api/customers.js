import api from '../api';

const base = '/app/api/customers';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listCustomers({ page = 1, perPage = 15, search = '' } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listAllCustomers({ search } = {}) {
    const { data } = await api.get(base, {
        params: {
            per_page: 500,
            ...(search ? { search } : {}),
        },
    });

    return data.data ?? [];
}

export async function createCustomer(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateCustomer(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchCustomer(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function deleteCustomer(id, payload = {}) {
    const dataPayload = typeof payload === 'string'
        ? { deleted_reason: payload, remark: payload }
        : { deleted_reason: payload?.deleted_reason ?? payload?.remark, remark: payload?.remark ?? payload?.deleted_reason };

    const { data } = await api.delete(`${base}/${id}`, {
        data: dataPayload,
    });
    return data ?? null;
}

