import api from '../api';

const base = '/app/api/permissions';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listPermissions(params = {}) {
    const { data } = await api.get(base, { params });

    return data;
}

export async function listAllPermissions() {
    const { data } = await api.get(base, { params: { per_page: 500 } });

    return data.data ?? [];
}

export async function createPermission(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updatePermission(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function deletePermission(id) {
    await api.delete(`${base}/${id}`);
}
