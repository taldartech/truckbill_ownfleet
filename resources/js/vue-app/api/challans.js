import api from '../api';

const challansBase = '/app/api/challans';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listChallans({ page = 1, perPage = 25, search = '', status = '' } = {}) {
    const { data } = await api.get(challansBase, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(status ? { status } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function createChallan(payload) {
    const { data } = await api.post(challansBase, payload);

    return unwrapResource(data);
}

export async function updateChallan(id, payload) {
    const { data } = await api.put(`${challansBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteChallan(id, payload = {}) {
    const data = typeof payload === 'string'
        ? { deleted_reason: payload, remark: payload }
        : { deleted_reason: payload?.deleted_reason ?? payload?.remark, remark: payload?.remark ?? payload?.deleted_reason };

    await api.delete(`${challansBase}/${id}`, {
        data,
    });
}
