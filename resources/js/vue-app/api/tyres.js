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

export async function fetchTyreAlerts() {
    const { data } = await api.get(`${base}/alerts`);
    return data.data ?? data;
}

export async function previewTyreRemoval(installationId, { stintKm, odometerKm } = {}) {
    const params = {};
    if (odometerKm != null && odometerKm !== '') {
        params.odometer_km = odometerKm;
    } else if (stintKm != null) {
        params.stint_km = stintKm;
    }

    const { data } = await api.get(`/app/api/tyre-installations/${installationId}/removal-preview`, {
        params,
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

export async function deleteTyre(id) {
    await api.delete(`${base}/${id}`);
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

export async function recordTyreDamage(id, payload) {
    const { data } = await api.post(`${base}/${id}/damage`, payload);
    return unwrapResource(data);
}

export async function recordTyreInspection(id, payload) {
    const { data } = await api.post(`${base}/${id}/inspections`, payload);
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

export async function getVehicleTyreHistory(vehicleId) {
    const { data } = await api.get(`/app/api/vehicles/${vehicleId}/tyre-history`);

    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        return { summary: {}, history: [] };
    }

    return {
        summary: data.summary ?? {},
        history: data.history ?? [],
    };
}

export async function downloadTyresTemplate() {
    const response = await api.get(
        `${base}/bulk/template`,
        {
            responseType: 'blob',
        },
    );

    return response.data;
}

export async function bulkUploadTyres(file, onUploadProgress) {
    const formData = new FormData();
    formData.append('file', file);

    const { data } = await api.post(
        `${base}/bulk`,
        formData,
        {
            onUploadProgress,
        },
    );

    return data;
}