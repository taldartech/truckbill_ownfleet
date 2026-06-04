import api from '../api';

const base = '/app/api/user-roles';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listUserRoles() {
    const { data } = await api.get(base);

    return data.data ?? [];
}

export async function fetchUserRole(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function syncRolePermissions(roleId, permissionIds) {
    const { data } = await api.put(`${base}/${roleId}/permissions`, {
        permission_ids: permissionIds,
    });

    return unwrapResource(data);
}
