import api from '../api';

const base = '/app/api/branches';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listBranches(params = {}) {
    const { data } = await api.get(base, { params });

    return data;
}

export async function createBranch(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateBranch(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteBranch(id) {
    await api.delete(`${base}/${id}`);
}
