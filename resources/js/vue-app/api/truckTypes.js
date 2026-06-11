import api from '../api';

const base = '/app/api/truck-types';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listAllTruckTypes() {
    const { data } = await api.get(base, {
        params: { per_page: 500 },
    });

    return data.data ?? [];
}

export async function createTruckType(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateTruckType(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}
