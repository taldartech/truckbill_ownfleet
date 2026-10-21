import api, { payloadNeedsMultipart, toFormData } from '../api';

const base = '/app/api/branches';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listBranches(params = {}) {
    const { data } = await api.get(base, { params });

    return data;
}

export async function listAllBranches(params = {}) {
    const { data } = await api.get(base, { params: { per_page: 500, ...params } });

    return data.data ?? [];
}

export async function createBranch(payload) {
    if (payloadNeedsMultipart(payload, ['branch_logo'])) {
        const { data } = await api.post(base, toFormData(payload));

        return unwrapResource(data);
    }

    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateBranch(id, payload) {
    if (payloadNeedsMultipart(payload, ['branch_logo'])) {
        const formData = toFormData(payload);
        formData.append('_method', 'PUT');
        const { data } = await api.post(`${base}/${id}`, formData);

        return unwrapResource(data);
    }

    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteBranch(id, payload = {}) {
    const dataPayload = typeof payload === 'string'
        ? { deleted_reason: payload, remark: payload }
        : { deleted_reason: payload?.deleted_reason ?? payload?.remark, remark: payload?.remark ?? payload?.deleted_reason };

    const { data } = await api.delete(`${base}/${id}`, {
        data: dataPayload,
    });

    return data ?? null;
}

export async function fetchBranchDeletionImpact(id) {
    const { data } = await api.get(`${base}/${id}/deletion-impact`);

    return data;
}

export async function fetchBranch(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function enterBranch(id) {
    const { data } = await api.post(`${base}/${id}/enter`);

    return data;
}

export async function switchBranch(branchId) {
    const { data } = await api.post(`${base}/switch`, { branch_id: branchId });

    return data;
}

export async function exitBranch() {
    const { data } = await api.post(`${base}/exit`);

    return data;
}
