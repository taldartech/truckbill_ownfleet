import api from '../api';

const base = '/app/api/batteries';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listBatteries({ page = 1, perPage = 15, search, status, brand, vehicleId, warranty } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(status ? { status } : {}),
            ...(brand ? { brand } : {}),
            ...(vehicleId ? { vehicle_id: vehicleId } : {}),
            ...(warranty ? { warranty } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function getBatteryCounts() {
    const { data } = await api.get(`${base}/counts`);
    return {
        counts: data.counts ?? {},
        brands: data.brands ?? [],
    };
}

export async function getInStockBatteries(brand = null) {
    const { data } = await api.get(`${base}/in-stock`, {
        params: brand ? { brand } : {},
    });
    return data.data ?? [];
}

export async function getBattery(id) {
    const { data } = await api.get(`${base}/${id}`);
    return {
        battery: data.data ?? unwrapResource(data),
        life_summary: data.life_summary ?? null,
        replacements: data.replacements ?? [],
    };
}

export async function createBatteryInspection(id, payload) {
    const { data } = await api.post(`${base}/${id}/inspections`, payload);
    return unwrapResource(data);
}

export async function createBattery(payload) {
    const { data } = await api.post(base, payload);
    return unwrapResource(data);
}

export async function updateBattery(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);
    return unwrapResource(data);
}

export async function deleteBattery(id) {
    await api.delete(`${base}/${id}`);
}

export async function scrapBattery(id, payload) {
    const { data } = await api.post(`${base}/${id}/scrap`, payload);
    return unwrapResource(data);
}

export async function installBattery(id, payload) {
    const { data } = await api.post(`${base}/${id}/install`, payload);
    return unwrapResource(data);
}

export async function replaceBattery(payload) {
    const { data } = await api.post(`${base}/replace`, payload);
    return unwrapResource(data);
}

export async function removeBatteryInstallation(installationId, payload = {}) {
    const { data } = await api.post(`/app/api/battery-installations/${installationId}/remove`, payload);
    return unwrapResource(data);
}

export async function getVehicleBatteries(vehicleId) {
    const { data } = await api.get(`/app/api/vehicles/${vehicleId}/batteries`);
    return {
        vehicle: data.vehicle ?? {},
        active_batteries: data.active_batteries ?? [],
        history: data.history ?? [],
        replacements: data.replacements ?? [],
    };
}
