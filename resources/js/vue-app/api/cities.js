import api from '../api';

const base = '/app/api/cities';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listCities({ page = 1, perPage = 15, stateId, search } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(stateId ? { state_id: stateId } : {}),
            ...(search ? { search } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function createCity(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateCity(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchCity(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

