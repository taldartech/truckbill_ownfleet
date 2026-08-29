import api from '../api';

const base = '/app/api/brokers';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listBrokers({ page = 1, perPage = 15, search = '' } = {}) {
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

export async function listAllBrokers({ search } = {}) {
    const { data } = await api.get(base, {
        params: {
            per_page: 500,
            ...(search ? { search } : {}),
        },
    });

    return data.data ?? [];
}

export async function createBroker(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateBroker(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchBroker(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function deleteBroker(id,payload) {
    const {data} = await api.delete(`${base}/${id}`, {
        data : payload,
    });

    return data ?? null;
}

