import api from '../api';

const base = '/app/api/vehicle-maintenances';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export const MAINTENANCE_DUE_STATUSES = [
    { value: '', label: 'All statuses' },
    { value: 'due', label: 'Due' },
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'none', label: 'No schedule' },
];

export const MAINTENANCE_OVERDUE_REASON_FILTERS = [
    { value: '', label: 'All overdue types' },
    { value: 'km', label: 'KM overdue' },
    { value: 'date', label: 'Date overdue' },
    { value: 'both', label: 'KM & Date overdue' },
];

export function defaultMaintenanceListFilters() {
    return {
        from_date: '',
        to_date: '',
        vehicle_id: '',
        maintenance_service_type_id: '',
        due_status: '',
    };
}

export function defaultMaintenanceDueFilters() {
    return {
        search: '',
        vehicle_id: '',
        maintenance_service_type_id: '',
        overdue_reason: '',
    };
}

export async function listVehicleMaintenances({
    page = 1,
    perPage = 10,
    search = '',
    vehicleId = '',
    maintenanceServiceTypeId = '',
    from = '',
    to = '',
    dueStatus = '',
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
            ...(dueStatus ? { due_status: dueStatus } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function fetchVehicleMaintenance(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function listDueVehicleMaintenances({
    search = '',
    vehicleId = '',
    maintenanceServiceTypeId = '',
    overdueReason = '',
} = {}) {
    const { data } = await api.get(`${base}/due`, {
        params: {
            ...(search ? { search } : {}),
            ...(vehicleId ? { vehicle_id: vehicleId } : {}),
            ...(maintenanceServiceTypeId ? { maintenance_service_type_id: maintenanceServiceTypeId } : {}),
            ...(overdueReason ? { overdue_reason: overdueReason } : {}),
        },
    });

    return data.data ?? [];
}

export async function listVehicleMaintenanceHistory(vehicleId, { page = 1, perPage = 10 } = {}) {
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
