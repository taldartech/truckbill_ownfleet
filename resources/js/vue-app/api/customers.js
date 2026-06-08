import api from '../api';

const base = '/app/api/customers';

function unwrapResource(payload) {
    return payload?.data ?? payload;
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

export async function deleteCustomer(id) {
    await api.delete(`${base}/${id}`);
}
