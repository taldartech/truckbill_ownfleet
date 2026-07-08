import { defaultTripListFilters } from '../api/trips';

export const TRIP_ARCHIVED_LIST_FLASH_QUERY_KEYS = ['denied'];

export const TRIP_ARCHIVED_LIST_FILTER_QUERY_KEYS = [
    'from_date',
    'to_date',
    'search',
    'status',
    'trip_type',
    'customer_id',
    'broker_id',
    'page',
    'per_page',
];

const STORAGE_PREFIX = 'truckbill:trip-archived-list-state';

function defaultListState() {
    return {
        ...defaultTripListFilters(),
        page: 1,
        per_page: 15,
    };
}

function storageKey(tenantId) {
    return `${STORAGE_PREFIX}:${tenantId ?? 'global'}`;
}

function readQueryValue(query, key) {
    const value = query[key];

    if (typeof value === 'string') {
        return value;
    }

    if (Array.isArray(value) && typeof value[0] === 'string') {
        return value[0];
    }

    return '';
}

function hasFilterQueryParams(query = {}) {
    return TRIP_ARCHIVED_LIST_FILTER_QUERY_KEYS.some((key) => readQueryValue(query, key) !== '');
}

export { hasFilterQueryParams as hasArchivedFilterQueryParams };

function normalizePage(value, fallback = 1) {
    const page = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(page) && page > 0 ? page : fallback;
}

function normalizePerPage(value, fallback = 15) {
    const perPage = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(perPage) && perPage > 0 ? perPage : fallback;
}

export function parseTripArchivedListStateFromQuery(query = {}) {
    const defaults = defaultListState();
    const search = readQueryValue(query, 'search').trim();

    return {
        from_date: readQueryValue(query, 'from_date') || defaults.from_date,
        to_date: readQueryValue(query, 'to_date') || defaults.to_date,
        search,
        status: readQueryValue(query, 'status'),
        trip_type: readQueryValue(query, 'trip_type'),
        customer_id: readQueryValue(query, 'customer_id'),
        broker_id: readQueryValue(query, 'broker_id'),
        page: normalizePage(readQueryValue(query, 'page'), defaults.page),
        per_page: normalizePerPage(readQueryValue(query, 'per_page'), defaults.per_page),
    };
}

export function buildTripArchivedListQuery(state = {}, { includeDefaults = false } = {}) {
    const defaults = defaultListState();
    const query = {};

    const assignIfPresent = (key, value) => {
        const normalized = value == null ? '' : String(value).trim();

        if (normalized === '' && !includeDefaults) {
            return;
        }

        if (!includeDefaults && normalized === String(defaults[key] ?? '').trim()) {
            return;
        }

        query[key] = normalized;
    };

    assignIfPresent('from_date', state.from_date);
    assignIfPresent('to_date', state.to_date);
    assignIfPresent('search', state.search);
    assignIfPresent('status', state.status);
    assignIfPresent('trip_type', state.trip_type);
    assignIfPresent('customer_id', state.customer_id);
    assignIfPresent('broker_id', state.broker_id);

    const page = normalizePage(state.page, defaults.page);

    if (includeDefaults || page > 1) {
        query.page = String(page);
    }

    const perPage = normalizePerPage(state.per_page, defaults.per_page);

    if (includeDefaults || perPage !== defaults.per_page) {
        query.per_page = String(perPage);
    }

    return query;
}

export function splitTripArchivedListRouteQuery(query = {}) {
    const flash = {};
    const filters = {};

    Object.entries(query).forEach(([key, value]) => {
        if (TRIP_ARCHIVED_LIST_FLASH_QUERY_KEYS.includes(key)) {
            flash[key] = Array.isArray(value) ? value[0] : value;

            return;
        }

        if (TRIP_ARCHIVED_LIST_FILTER_QUERY_KEYS.includes(key)) {
            filters[key] = Array.isArray(value) ? value[0] : value;
        }
    });

    return { flash, filters };
}

export function loadTripArchivedListStateFromSession(tenantId) {
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

export function saveTripArchivedListStateToSession(tenantId, state) {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(storageKey(tenantId), JSON.stringify(state));
}

export function clearTripArchivedListStateFromSession(tenantId) {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(storageKey(tenantId));
}

export function resolveTripArchivedListState(query = {}, tenantId = null) {
    if (hasFilterQueryParams(query)) {
        return parseTripArchivedListStateFromQuery(query);
    }

    const stored = loadTripArchivedListStateFromSession(tenantId);

    if (stored) {
        return stored;
    }

    return defaultListState();
}

export function mergeTripArchivedListQuery(flashQuery = {}, tenantId = null) {
    const stored = loadTripArchivedListStateFromSession(tenantId) ?? defaultListState();

    return {
        ...buildTripArchivedListQuery(stored),
        ...flashQuery,
    };
}

export function tripArchivedListIndexLocation(extraQuery = {}, tenantId = null) {
    return {
        name: 'trips.archived',
        query: mergeTripArchivedListQuery(extraQuery, tenantId),
    };
}
