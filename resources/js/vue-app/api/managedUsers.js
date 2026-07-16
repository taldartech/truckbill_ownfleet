import api from '../api';

const base = '/app/api/managed-users';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listManagedUsers(params = {}) {
    const { data } = await api.get(base, { params });

    return data;
}

export async function listAssignableRoles() {
    const { data } = await api.get(`${base}/assignable-roles`);

    return data.data ?? [];
}

export async function createManagedUser(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateManagedUser(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchManagedUser(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function deleteManagedUser(id) {
    await api.delete(`${base}/${id}`);
}
