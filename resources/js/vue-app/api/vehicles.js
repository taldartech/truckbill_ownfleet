import api from '../api';

const vehiclesBase = '/app/api/vehicles';

export const VEHICLE_TYPES = [
    { value: 'TRUCK', label: 'Truck' },
    { value: 'TRAILER', label: 'Trailer' },
    { value: 'TANKER', label: 'Tanker' },
    { value: 'CONTAINER', label: 'Container' },
];

export function defaultVehicleListFilters() {
    return {
        search: '',
        vehicle_type: '',
        status: '',
        primary_driver: '',
    };
}

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function searchVehiclesForTripSelection({
    search = '',
    page = 1,
    perPage = 20,
    asOfDate = null,
    endAt = null,
} = {}) {
    const { data } = await api.get(`${vehiclesBase}/for-trip-selection`, {
        params: {
            search,
            page,
            per_page: perPage,
            include_ineligible: 1,
            ...(asOfDate ? { as_of_date: asOfDate } : {}),
            ...(endAt ? { end_at: endAt } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listAllVehicles({ activeOnly = false } = {}) {
    const { data } = await api.get(vehiclesBase, {
        params: {
            per_page: 500,
            ...(activeOnly ? { active_only: 1 } : {}),
        },
    });

    return data.data ?? [];
}

export async function listVehicles({
    page = 1,
    perPage = 10,
    search = '',
    vehicleType = '',
    status = '',
    primaryDriver = '',
} = {}) {
    const { data } = await api.get(vehiclesBase, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(vehicleType ? { vehicle_type: vehicleType } : {}),
            ...(status ? { status } : {}),
            ...(primaryDriver ? { primary_driver: primaryDriver } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function createVehicle(payload) {
    const { data } = await api.post(vehiclesBase, payload);

    return unwrapResource(data);
}

export async function updateVehicle(id, payload) {
    const { data } = await api.put(`${vehiclesBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function toggleVehicleStatus(id, isActive) {
    const { data } = await api.patch(`${vehiclesBase}/${id}/status`, {
        is_active: isActive,
    });

    return unwrapResource(data);
}

export async function deleteVehicle(id) {
    await api.delete(`${vehiclesBase}/${id}`);
}

export async function fetchVehiclePrimaryAssignment(vehicleId) {
    const { data } = await api.get(`${vehiclesBase}/${vehicleId}/primary-assignment`);

    return data;
}
