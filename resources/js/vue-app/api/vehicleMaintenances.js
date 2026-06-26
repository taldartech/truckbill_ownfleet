import api from '../api';

const base = '/app/api/vehicle-maintenances';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listVehicleMaintenances({
    page = 1,
    perPage = 25,
    search = '',
    vehicleId = '',
    maintenanceServiceTypeId = '',
    from = '',
    to = '',
} = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(vehicleId ? { vehicle_id: vehicleId } : {}),
            ...(maintenanceServiceTypeId ? { maintenance_service_type_id: maintenanceServiceTypeId } : {}),
            ...(from ? { from } : {}),
            ...(to ? { to } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listDueVehicleMaintenances() {
    const { data } = await api.get(`${base}/due`);

    return data.data ?? [];
}

export async function listVehicleMaintenanceHistory(vehicleId, { page = 1, perPage = 25 } = {}) {
    const { data } = await api.get(`/app/api/vehicles/${vehicleId}/maintenances`, {
        params: { page, per_page: perPage },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function createVehicleMaintenance(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateVehicleMaintenance(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteVehicleMaintenance(id) {
    await api.delete(`${base}/${id}`);
}
