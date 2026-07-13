import { defaultMaintenanceListFilters } from '../api/vehicleMaintenances';

export const MAINTENANCE_RECORDS_FLASH_QUERY_KEYS = ['created', 'updated', 'deleted'];

const STORAGE_PREFIX = 'truckbill:maintenance-records-list-state';
const SOURCE_STORAGE_KEY = 'truckbill:maintenance-list-return-source';

function defaultListState() {
    return {
        ...defaultMaintenanceListFilters(),
        page: 1,
        per_page: 10,
        filters_applied: true,
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

export function loadMaintenanceRecordsListStateFromSession(tenantId) {
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
            filters_applied: Boolean(parsed?.filters_applied),
        };
    } catch {
        return null;
    }
}

export function saveMaintenanceRecordsListStateToSession(tenantId, state) {
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
            filters_applied: Boolean(state?.filters_applied),
        }),
    );
}

export function setMaintenanceListReturnSource(source) {
    if (typeof window === 'undefined') {
        return;
    }

    if (source !== 'records' && source !== 'due') {
        return;
    }

    window.sessionStorage.setItem(SOURCE_STORAGE_KEY, source);
}

export function getMaintenanceListReturnSource() {
    if (typeof window === 'undefined') {
        return 'records';
    }

    const source = window.sessionStorage.getItem(SOURCE_STORAGE_KEY);

    return source === 'due' ? 'due' : 'records';
}

export function resolveMaintenanceRecordsListState(tenantId = null) {
    return loadMaintenanceRecordsListStateFromSession(tenantId) ?? defaultListState();
}

export function mergeMaintenanceRecordsListQuery(flashQuery = {}, tenantId = null) {
    const stored = resolveMaintenanceRecordsListState(tenantId);
    const query = {};

    if (stored.from_date) {
        query.from_date = stored.from_date;
    }

    if (stored.to_date) {
        query.to_date = stored.to_date;
    }

    if (stored.vehicle_id) {
        query.vehicle_id = stored.vehicle_id;
    }

    if (stored.maintenance_service_type_id) {
        query.maintenance_service_type_id = String(stored.maintenance_service_type_id);
    }

    if (stored.due_status) {
        query.due_status = stored.due_status;
    }

    if (stored.filters_applied) {
        query.filters_applied = '1';
    }

    if (Number(stored.page) > 1) {
        query.page = String(stored.page);
    }

    return {
        ...query,
        ...flashQuery,
    };
}

export function maintenanceRecordsListIndexLocation(extraQuery = {}, tenantId = null) {
    return {
        name: 'maintenance.records',
        query: mergeMaintenanceRecordsListQuery(extraQuery, tenantId),
    };
}

export function maintenanceRecordsListLocationForServiceDate(serviceDate, flashQuery = {}, tenantId = null) {
    const date = serviceDate || '';
    const state = {
        from_date: date,
        to_date: date,
        vehicle_id: '',
        maintenance_service_type_id: '',
        due_status: '',
        page: 1,
        per_page: 10,
        filters_applied: Boolean(date),
    };

    setMaintenanceListReturnSource('records');
    saveMaintenanceRecordsListStateToSession(tenantId, state);

    return {
        name: 'maintenance.records',
        query: {
            ...(date
                ? {
                      from_date: date,
                      to_date: date,
                      filters_applied: '1',
                  }
                : {}),
            ...flashQuery,
        },
    };
}
