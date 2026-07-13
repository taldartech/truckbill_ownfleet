import { defaultMaintenanceDueFilters } from '../api/vehicleMaintenances';

export const MAINTENANCE_DUE_FLASH_QUERY_KEYS = ['created', 'updated', 'deleted'];

const STORAGE_PREFIX = 'truckbill:maintenance-due-list-state';

function defaultListState() {
    return {
        ...defaultMaintenanceDueFilters(),
        page: 1,
        per_page: 10,
    };
}

function storageKey(tenantId) {
    return `${STORAGE_PREFIX}:${tenantId ?? 'global'}`;
}

function normalizePage(value, fallback = 1) {
    const page = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(page) && page > 0 ? page : fallback;
}

function normalizePerPage(value, fallback = 10) {
    const perPage = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(perPage) && perPage > 0 ? perPage : fallback;
}

export function loadMaintenanceDueListStateFromSession(tenantId) {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(storageKey(tenantId));

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        return {
            ...defaultListState(),
            ...parsed,
            page: normalizePage(parsed?.page),
            per_page: normalizePerPage(parsed?.per_page),
        };
    } catch {
        return null;
    }
}

export function saveMaintenanceDueListStateToSession(tenantId, state) {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(
        storageKey(tenantId),
        JSON.stringify({
            ...defaultListState(),
            ...state,
            page: normalizePage(state?.page),
            per_page: normalizePerPage(state?.per_page),
        }),
    );
}

export function resolveMaintenanceDueListState(tenantId = null) {
    return loadMaintenanceDueListStateFromSession(tenantId) ?? defaultListState();
}

export function mergeMaintenanceDueListQuery(flashQuery = {}, tenantId = null) {
    const stored = resolveMaintenanceDueListState(tenantId);
    const query = { ...flashQuery };

    if (stored.from_date) {
        query.from_date = stored.from_date;
    }

    if (stored.to_date) {
        query.to_date = stored.to_date;
    }

    if (stored.search) {
        query.search = stored.search;
    }

    if (stored.vehicle_id) {
        query.vehicle_id = stored.vehicle_id;
    }

    if (stored.maintenance_service_type_id) {
        query.maintenance_service_type_id = String(stored.maintenance_service_type_id);
    }

    if (stored.overdue_reason) {
        query.overdue_reason = stored.overdue_reason;
    }

    if (Number(stored.page) > 1) {
        query.page = String(stored.page);
    }

    return query;
}

export function maintenanceDueListIndexLocation(extraQuery = {}, tenantId = null) {
    return {
        name: 'maintenance.due',
        query: mergeMaintenanceDueListQuery(extraQuery, tenantId),
    };
}
