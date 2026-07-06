import api from '../api';

const vehiclesBase = '/app/api/vehicles';

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

/**
 * @param {Array<{ id: string, registration_number?: string, vehicle_type?: string, vehicle_type_label?: string, make?: string, model?: string, is_active?: boolean }>} vehicles
 */
export function buildVehicleSelectOptions(vehicles) {
    return [...(vehicles ?? [])]
        .sort((left, right) => {
            const leftActive = left.is_active !== false;
            const rightActive = right.is_active !== false;

            if (leftActive !== rightActive) {
                return leftActive ? -1 : 1;
            }

            return String(left.registration_number ?? '').localeCompare(String(right.registration_number ?? ''));
        })
        .map((vehicle) => {
            const registration = vehicle.registration_number ?? '—';
            const inactive = vehicle.is_active === false;

            return {
                id: vehicle.id,
                label: registration,
                inactive,
                badge: inactive ? '(Inactive)' : '',
                searchText: registration,
            };
        });
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

export async function fetchVehicle(id) {
    const { data } = await api.get(`${vehiclesBase}/${id}`);

    return unwrapResource(data);
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
