import api from '../api';

const base = '/app/api/goods-types';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listGoodsTypes({ page = 1, perPage = 15, search = '' } = {}) {
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

export async function listAllGoodsTypes({ search } = {}) {
    const { data } = await api.get(base, {
        params: {
            per_page: 500,
            ...(search ? { search } : {}),
        },
    });

    return data.data ?? [];
}

export async function createGoodsType(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateGoodsType(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchGoodsType(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function deleteGoodsType(id) {
    await api.delete(`${base}/${id}`);
}
