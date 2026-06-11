import api from '../api';

const base = '/app/api/driver-assignments';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listDriverAssignments({ vehicleId, page = 1, perPage = 50 } = {}) {
    const params = { page, per_page: perPage };

    if (vehicleId) {
        params.vehicle_id = vehicleId;
    }

    const { data } = await api.get(base, { params });

    return data;
}

export async function createDriverAssignment(payload) {
    const { data } = await api.post(base, {
        ...payload,
        is_primary: payload.is_primary ?? true,
    });

    return unwrapResource(data);
}

export async function updateDriverAssignment(assignmentId, payload) {
    const { data } = await api.put(`${base}/${assignmentId}`, {
        ...payload,
        is_primary: payload.is_primary ?? true,
    });

    return unwrapResource(data);
}

export async function deleteDriverAssignment(assignmentId) {
    await api.delete(`${base}/${assignmentId}`);
}

export async function removeVehiclePrimaryDriver(vehicleId) {
    const { data } = await api.post(`/app/api/vehicles/${vehicleId}/remove-primary-driver`);

    return data;
}
