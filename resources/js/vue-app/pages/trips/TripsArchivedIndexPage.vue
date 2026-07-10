<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing archived trips.
        </div>

        <AppIndexListCard title="Archived Trips" :loading="loading" loading-text="Loading archived trips…">
            <template #toolbar>
                <RouterLink :to="{ name: 'trips.index' }" class="btn btn-back btn-sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Back to Trip List
                </RouterLink>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="trips-archived-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="trips-archived-filters-panel"
                :loading="loading"
                @search="loadTrips(1)"
                @reset="clearFilters"
            >
                <div class="trips-filter-search">
                    <label class="form-label" for="trips_archived_search">Search</label>
                    <AppIndexSearchInput
                        v-model="filters.search"
                        input-id="trips_archived_search"
                        placeholder="Trip #, truck registration, primary or substitute driver…"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadTrips(1)"
                    />
                    <div v-if="searchIgnoresDateRange" class="form-text mb-0 mt-1">
                        Search looks across all trips and ignores the date range.
                    </div>
                </div>
                <div class="trips-filter-period">
                    <DashboardDateRangePicker
                        v-model:from-date="filters.from_date"
                        v-model:to-date="filters.to_date"
                        label="Trip Date"
                        panel-align="start"
                        :preset-keys="tripDatePresetKeys"
                        :block-future-dates="false"
                        @change="onDateRangeChange"
                    />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <AppSelect
                        v-model="filters.status"
                        :options="tripStatuses"
                        placeholder="All statuses"
                        search-placeholder="Search status…"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label">Trip Type</label>
                    <AppSelect
                        v-model="filters.trip_type"
                        :options="tripTypes"
                        placeholder="All trip types"
                        search-placeholder="Search trip type…"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label">Customer</label>
                    <SearchablePicker
                        v-model="filters.customer_id"
                        :items="customers"
                        placeholder="All customers"
                        search-placeholder="Search customer…"
                        empty-message="No customer found."
                        view-title="Customer details"
                    />
                </div>
                <div>
                    <label class="form-label">Reference</label>
                    <SearchablePicker
                        v-model="filters.broker_id"
                        :items="brokers"
                        placeholder="All references"
                        search-placeholder="Search reference…"
                        empty-message="No reference found."
                        view-title="Reference details"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Trip #</th>
                        <th>Truck</th>
                        <th>Driver</th>
                        <th class="trips-status-col">Status</th>
                        <th class="trips-archived-col">Archived At</th>
                        <th class="trips-archive-reason-col">Archive Reason</th>
                        <th v-if="showActions" class="app-index-actions-cell text-end" style="width: 80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="trips.length === 0" class="app-index-empty-row">
                        <td :colspan="tableColspan" class="text-center text-muted py-4">
                            No archived trips found for selected filters.
                        </td>
                    </tr>
                    <tr v-for="trip in trips" :key="trip.id">
                        <td>
                            <div class="trips-trip-cell">
                                <RouterLink
                                    v-if="canOpenTrip"
                                    :to="{ name: 'trips.show', params: { id: trip.id } }"
                                    class="trip-number-link fw-semibold text-decoration-none"
                                >
                                    {{ trip.trip_number || '—' }}
                                </RouterLink>
                                <strong v-else>{{ trip.trip_number || '—' }}</strong>
                                <span
                                    class="trip-type-badge"
                                    :class="tripTypeInfo(trip.trip_type).badge"
                                    :title="tripTypeInfo(trip.trip_type).label"
                                >
                                    <i class="bi" :class="tripTypeInfo(trip.trip_type).icon" aria-hidden="true"></i>
                                    <span class="trip-type-badge__label">{{ tripTypeInfo(trip.trip_type).label }}</span>
                                </span>
                            </div>
                        </td>
                        <td>{{ trip.vehicle?.registration_number ?? '—' }}</td>
                        <td>
                            <div>{{ trip.driver?.name ?? '—' }}</div>
                            <div v-if="trip.substitute_driver?.name" class="small text-muted">
                                Substitute: {{ trip.substitute_driver.name }}
                            </div>
                        </td>
                        <td class="trips-status-col">
                            <span class="badge" :class="tripStatusInfo(trip.status).badge">
                                {{ tripStatusInfo(trip.status).label }}
                            </span>
                        </td>
                        <td class="trips-archived-col small">
                            {{ formatDate(trip.archived_at) }}
                        </td>
                        <td class="trips-archive-reason-col small text-muted">
                            {{ trip.archive_reason || '—' }}
                        </td>
                        <td v-if="showActions" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton action="view" title="View trip" @click="goToView(trip)" />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="trips"
                    aria-label="Archived trips pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, inject, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import { listAllBrokers } from '../../api/brokers';
import { listAllCustomers } from '../../api/customers';
import {
    defaultTripListFilters,
    listTrips,
    tripStatusMeta,
    tripTypeMeta,
    TRIP_STATUSES,
    TRIP_TYPES,
} from '../../api/trips';
import DashboardDateRangePicker from '../../components/dashboard/DashboardDateRangePicker.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import SearchablePicker from '../../components/ui/SearchablePicker.vue';
import { TRIP_LIST_DATE_PRESET_KEYS } from '../../utils/dateRangePresets';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatDate } from '../../utils/datetime';
import {
    buildTripArchivedListQuery,
    clearTripArchivedListStateFromSession,
    hasArchivedFilterQueryParams,
    loadTripArchivedListStateFromSession,
    resolveTripArchivedListState,
    saveTripArchivedListStateToSession,
    splitTripArchivedListRouteQuery,
} from '../../utils/tripArchivedListFilterState';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const currentUser = inject('currentUser', ref(null));
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions(currentUser);
const canView = computed(() => canInCompany('trips.view'));
const canOpenTrip = computed(() => canView.value);
const showActions = computed(() => canOpenTrip.value);
const tableColspan = computed(() => (showActions.value ? 7 : 6));

const { applyApiError } = useApiFormErrors();

const trips = ref([]);
const customers = ref([]);
const brokers = ref([]);
const loading = ref(true);
const tripStatuses = TRIP_STATUSES;
const tripTypes = TRIP_TYPES;
const tripDatePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;
const filtersOpen = ref(false);

const filters = reactive(defaultArchivedFilters());

const { pagination, syncFromMeta } = useListPagination(10);

const defaultFilters = defaultArchivedFilters();
const searchAutoLoadReady = ref(false);
const listReady = ref(false);
let searchDebounceTimer = null;
let skipNextSearchWatch = false;
let skipNextRouteSync = false;

const tenantId = computed(
    () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
);

const searchIgnoresDateRange = computed(() => filters.search.trim() !== '');

const filtersActive = computed(() => {
    return (
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.search.trim() !== '' ||
        filters.customer_id !== '' ||
        filters.broker_id !== '' ||
        filters.status !== '' ||
        filters.trip_type !== ''
    );
});

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function defaultArchivedFilters() {
    return defaultTripListFilters();
}

function currentListState() {
    return {
        from_date: filters.from_date,
        to_date: filters.to_date,
        search: filters.search.trim(),
        status: filters.status,
        trip_type: filters.trip_type,
        customer_id: filters.customer_id,
        broker_id: filters.broker_id,
        page: pagination.currentPage,
        per_page: pagination.perPage,
    };
}

function applyListState(state) {
    filters.from_date = state.from_date ?? defaultFilters.from_date;
    filters.to_date = state.to_date ?? defaultFilters.to_date;
    filters.search = state.search ?? '';
    filters.status = state.status ?? '';
    filters.trip_type = state.trip_type ?? '';
    filters.customer_id = state.customer_id ?? '';
    filters.broker_id = state.broker_id ?? '';
    pagination.currentPage = Number(state.page) > 0 ? Number(state.page) : 1;
    pagination.perPage = Number(state.per_page) > 0 ? Number(state.per_page) : 10;
}

function listStatesEqual(left, right) {
    return (
        left.from_date === right.from_date &&
        left.to_date === right.to_date &&
        left.search === right.search &&
        left.status === right.status &&
        left.trip_type === right.trip_type &&
        String(left.customer_id) === String(right.customer_id) &&
        String(left.broker_id) === String(right.broker_id) &&
        Number(left.page) === Number(right.page) &&
        Number(left.per_page) === Number(right.per_page)
    );
}

function syncListStateToRoute(extraQuery = {}) {
    const state = currentListState();

    saveTripArchivedListStateToSession(tenantId.value, state);

    const nextQuery = {
        ...buildTripArchivedListQuery(state),
        ...extraQuery,
    };

    skipNextRouteSync = true;
    router
        .replace({ query: nextQuery })
        .catch(() => {})
        .finally(() => {
            skipNextRouteSync = false;
        });
}

function tripTypeInfo(type) {
    return tripTypeMeta(type);
}

function tripStatusInfo(status) {
    return tripStatusMeta(status);
}

function showRouteFlash() {
    const { flash: flashQuery, filters: filterQuery } = splitTripArchivedListRouteQuery(route.query);

    if (flashQuery.denied === '1') {
        toast.error('You do not have permission to view archived trips, or company context is missing.');
        router.replace({ query: buildTripArchivedListQuery(resolveTripArchivedListState(filterQuery, tenantId.value)) });
    }
}

function clearFilters() {
    clearTimeout(searchDebounceTimer);
    skipNextSearchWatch = true;
    clearTripArchivedListStateFromSession(tenantId.value);
    Object.assign(filters, defaultArchivedFilters());
    pagination.currentPage = 1;
    pagination.perPage = 10;
    syncListStateToRoute();
    loadTrips(1);
}

function scheduleSearchLoad() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        loadTrips(1);
    }, 350);
}

function onDateRangeChange() {
    loadTrips(1);
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadTrips(page);
}

async function loadTrips(page = 1) {
    loading.value = true;

    const search = filters.search.trim();

    try {
        const result = await listTrips({
            page,
            perPage: pagination.perPage,
            archived: 'archived',
            search: search || undefined,
            from_date: search ? undefined : filters.from_date || undefined,
            to_date: search ? undefined : filters.to_date || undefined,
            customer_id: filters.customer_id || undefined,
            broker_id: filters.broker_id || undefined,
            status: filters.status || undefined,
            trip_type: filters.trip_type || undefined,
        });

        trips.value = result.data;
        syncFromMeta(result.meta, trips.value.length);
        syncListStateToRoute();
    } catch (error) {
        if (!applyApiError(error, 'Failed to load archived trips.')) {
            toast.error(friendlyApiErrorMessage(error, 'Failed to load archived trips.'));
        }
    } finally {
        loading.value = false;
    }
}

function goToView(trip) {
    saveTripArchivedListStateToSession(tenantId.value, currentListState());
    router.push({ name: 'trips.show', params: { id: trip.id } });
}

watch(
    () => route.query,
    () => {
        showRouteFlash();

        if (route.name !== 'trips.archived' || skipNextRouteSync || !listReady.value) {
            return;
        }

        const nextState = resolveTripArchivedListState(route.query, tenantId.value);
        const activeState = currentListState();

        if (!listStatesEqual(nextState, activeState)) {
            applyListState(nextState);
            loadTrips(nextState.page);
        }
    },
    { immediate: true },
);

watch(
    () => filters.search,
    () => {
        if (!searchAutoLoadReady.value) {
            return;
        }

        if (skipNextSearchWatch) {
            skipNextSearchWatch = false;

            return;
        }

        scheduleSearchLoad();
    },
);

async function loadFilterLookups() {
    const [customerList, brokerList] = await Promise.all([listAllCustomers(), listAllBrokers()]);

    customers.value = customerList;
    brokers.value = brokerList;
}

function applyRouteFilters() {
    const nextState = resolveTripArchivedListState(route.query, tenantId.value);

    applyListState(nextState);
}

async function refreshUserContext() {
    try {
        const freshUser = await fetchCurrentUser();

        if (!freshUser) {
            return;
        }

        window.__AUTH_USER__ = freshUser;

        if (currentUser.value) {
            currentUser.value = freshUser;
        }
    } catch {
        // Keep the existing client user when refresh fails.
    }
}

onMounted(async () => {
    await refreshUserContext();
    applyRouteFilters();

    if (!needsCompanySelection.value) {
        await loadFilterLookups();
    }

    const initialPage = pagination.currentPage;
    await loadTrips(initialPage);

    if (!hasArchivedFilterQueryParams(route.query) && loadTripArchivedListStateFromSession(tenantId.value)) {
        syncListStateToRoute();
    }

    if (hasArchivedFilterQueryParams(route.query) || filtersActive.value) {
        filtersOpen.value = true;
    }

    listReady.value = true;
    searchAutoLoadReady.value = true;
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
    saveTripArchivedListStateToSession(tenantId.value, currentListState());
});
</script>

<style scoped>
.trips-filter-search {
    grid-column: 1 / -1;
}

.trips-filter-search :deep(.app-index-search) {
    max-width: none;
}

.trips-filter-period {
    min-width: 0;
}

.trip-number-link {
    color: #0d6efd;
}

.trip-number-link:hover {
    color: #0a58ca;
    text-decoration: underline !important;
}

.trips-trip-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.3rem;
    min-width: 8.5rem;
}

.trip-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    border: 1px solid transparent;
}

.trip-type-badge--round {
    color: #0b5ed7;
    background: #e7f1ff;
    border-color: #b6d4fe;
}

.trip-type-badge--one-way {
    color: #146c43;
    background: #e8f5ee;
    border-color: #a3cfbb;
}

.trip-type-badge__label {
    display: inline;
}

.trips-status-col {
    width: 1%;
    white-space: nowrap;
    vertical-align: middle;
}

.trips-archived-col {
    width: 1%;
    white-space: nowrap;
}

.trips-archive-reason-col {
    max-width: 14rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.app-index-list-card :deep(.app-table-actions) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.app-index-list-card :deep(.app-table-actions .btn-icon) {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px;
    padding: 0 !important;
    flex-shrink: 0;
}

.app-index-list-card :deep(.app-table-actions .btn-icon i) {
    font-size: 0.8125rem;
    line-height: 1;
}

.app-index-list-card :deep(.table) {
    font-size: 0.8125rem;
}

.app-index-list-card :deep(.table th),
.app-index-list-card :deep(.table td) {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}

@media (max-width: 767.98px) {
    .trip-type-badge__label {
        display: none;
    }
}
</style>
