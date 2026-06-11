import api from '../api';

const base = '/app/api/tyres';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listTyres({ page = 1, perPage = 15, search, status, vehicleId } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(status ? { status } : {}),
            ...(vehicleId ? { vehicle_id: vehicleId } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function getTyre(id) {
    const { data } = await api.get(`${base}/${id}`);
    return {
        tyre: data.data ?? unwrapResource(data),
        life_summary: data.life_summary ?? null,
        timeline: data.timeline ?? [],
    };
}

export async function previewTyreRemoval(installationId, stintKm) {
    const { data } = await api.get(`/app/api/tyre-installations/${installationId}/removal-preview`, {
        params: stintKm != null ? { stint_km: stintKm } : {},
    });
    return data;
}

export async function updateTyreInstallationStintKm(installationId, payload) {
    const { data } = await api.patch(`/app/api/tyre-installations/${installationId}/stint-km`, payload);
    return unwrapResource(data);
}

export async function addTyreExpense(id, payload) {
    const { data } = await api.post(`${base}/${id}/expenses`, payload);
    return unwrapResource(data);
}

export async function createTyre(payload) {
    const { data } = await api.post(base, payload);
    return unwrapResource(data);
}

export async function updateTyre(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);
    return unwrapResource(data);
}

export async function installTyre(id, payload) {
    const { data } = await api.post(`${base}/${id}/install`, payload);
    return unwrapResource(data);
}

export async function removeTyreInstallation(installationId, payload = {}) {
    const { data } = await api.post(`/app/api/tyre-installations/${installationId}/remove`, payload);
    return unwrapResource(data);
}

export async function rotateTyre(id, payload) {
    const { data } = await api.post(`${base}/${id}/rotate`, payload);
    return unwrapResource(data);
}

export async function retreadTyre(id, payload) {
    const { data } = await api.post(`${base}/${id}/retread`, payload);
    return unwrapResource(data);
}

export async function recordTyreDamage(id, payload) {
    const { data } = await api.post(`${base}/${id}/damage`, payload);
    return unwrapResource(data);
}

export async function replaceTyre(payload) {
    const { data } = await api.post(`${base}/replace`, payload);
    return unwrapResource(data);
}

export async function scrapTyre(id, payload) {
    const { data } = await api.post(`${base}/${id}/scrap`, payload);
    return unwrapResource(data);
}

export async function getVehicleTyrePositions(vehicleId) {
    const { data } = await api.get(`/app/api/vehicles/${vehicleId}/tyre-positions`);
    return data.data ?? [];
}
