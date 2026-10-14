import api from '../api';

const driversBase = '/app/api/drivers';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listDrivers({ page = 1, perPage = 25, search = '' } = {}) {
    const { data } = await api.get(driversBase, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listAllDrivers({ activeOnly = false, availableOnDate = null, excludeVehicleId = null, excludeAssignmentId = null } = {}) {
    const { data } = await api.get(driversBase, {
        params: {
            per_page: 500,
            ...(activeOnly ? { active_only: 1 } : {}),
            ...(availableOnDate ? { available_on_date: availableOnDate } : {}),
            ...(excludeVehicleId ? { exclude_vehicle_id: excludeVehicleId } : {}),
            ...(excludeAssignmentId ? { exclude_assignment_id: excludeAssignmentId } : {}),
        },
    });

    return data.data ?? [];
}

export async function fetchDriver(id) {
    const { data } = await api.get(`${driversBase}/${id}`);

    return unwrapResource(data);
}

export async function createDriver(payload) {
    const { data } = await api.post(driversBase, payload);

    return unwrapResource(data);
}

export async function updateDriver(id, payload) {
    const { data } = await api.put(`${driversBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function listLicenseExpiringDrivers({ days = 30 } = {}) {
    const { data } = await api.get(`${driversBase}/license-expiring`, {
        params: { days },
    });

    return data.data ?? [];
}

export async function deleteDriver(id, payload = {}) {
    const data = typeof payload === 'string'
        ? { deleted_reason: payload, remark: payload }
        : { deleted_reason: payload?.deleted_reason ?? payload?.remark, remark: payload?.remark ?? payload?.deleted_reason };

    await api.delete(`${driversBase}/${id}`, {
        data,
    });
}
