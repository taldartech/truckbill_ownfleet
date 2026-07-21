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
            </template>

            <AppIndexCollapsibleFilters
                panel-id="trips-archived-filters-panel"
                :loading="loading"
                @search="loadTrips(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="trips_archived_search">Search</label>
                    <AppIndexSearchInput
                        v-model="filters.search"
                        input-id="trips_archived_search"
                        placeholder="Trip #, truck, driver"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadTrips(1)"
                    />
                </div>
                <div class="filter-period-col">
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
                <div class="filter-select-col">
                    <label class="form-label">Status</label>
                    <AppSelect
                        v-model="filters.status"
                        :options="tripStatuses"
                        placeholder="All statuses"
                        search-placeholder="Search status…"
                        :disabled="loading"
                    />
                </div>
                <div class="filter-picker-col">
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
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Trip #</th>
                        <th>Truck / Driver</th>
                        <th class="trips-status-col">Status</th>
                        <th class="trips-period-col">Trip Date</th>
                        <th class="trips-route-col">Route</th>
                        <th class="trips-financials-col">Freight / Expense / Profit</th>
                        <th v-if="showActions" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
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
                        <td>
                            <div class="fw-bold">
                                {{ trip.vehicle?.registration_number ?? '—' }}
                            </div>

                            <div class="text-muted small">
                                {{ trip.driver?.name ?? '—' }}
                            </div>

                            <div
                                v-if="trip.substitute_driver?.name"
                                class="text-warning small"
                            >
                                Substitute: {{ trip.substitute_driver.name }}
                            </div>
                        </td>
                        <td class="trips-status-col">
                            <span class="badge" :class="tripStatusInfo(trip.status).badge">
                                {{ tripStatusInfo(trip.status).label }}
                            </span>
                        </td>
                        <td class="trips-period-col small">
                            <div>{{ formatDate(trip.start_at) }}</div>
                            <div class="text-muted">{{ formatDate(trip.end_at) }}</div>
                        </td>
                        <td class="trips-route-col small">
                            <div
                                class="trips-route"
                                :class="{ 'trips-route--has-via': routeSummary(trip).middle.length > 0 }"
                                :title="routeHoverTitle(trip)"
                            >
                                <div class="trips-route__main">
                                    <span class="trips-route__point trips-route__point--from">
                                        <i class="bi bi-circle-fill" aria-hidden="true"></i>
                                        <span class="trips-route__name">{{ routeSummary(trip).source }}</span>
                                    </span>
                                    <i class="bi bi-arrow-right trips-route__arrow" aria-hidden="true"></i>
                                    <span class="trips-route__point trips-route__point--to">
                                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                                        <span class="trips-route__name">{{ routeSummary(trip).destination }}</span>
                                    </span>
                                    <span
                                        v-if="routeSummary(trip).middle.length > 0"
                                        class="trips-route__via-badge"
                                    >
                                        +{{ routeSummary(trip).middle.length }}
                                    </span>
                                </div>
                                <div
                                    v-if="routeSummary(trip).middle.length > 0"
                                    class="trips-route__via"
                                >
                                    <span class="trips-route__via-label">Via</span>
                                    <span class="trips-route__via-stops">{{ routeSummary(trip).middle.join(' · ') }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="trips-financials-col">
                            <div class="trips-financials" :title="financialSummaryTitle(trip)">
                                <div class="trips-financials__row">
                                    <span class="trips-financials__label">Freight</span>
                                    <span class="trips-financials__val">₹{{ formatCompactMoney(trip.total_freight_amount) }}</span>
                                </div>
                                <div class="trips-financials__row">
                                    <span class="trips-financials__label">Expense</span>
                                    <span class="trips-financials__val">₹{{ formatCompactMoney(trip.total_expense_amount) }}</span>
                                </div>
                                <div class="trips-financials__row">
                                    <span class="trips-financials__label">Profit</span>
                                    <span
                                        class="trips-financials__val"
                                        :class="tripProfit(trip) >= 0 ? 'text-success' : 'text-danger'"
                                    >
                                        ₹{{ formatCompactMoney(tripProfit(trip)) }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td
                            v-if="showActions"
                            class="app-index-actions-cell"
                            style="text-align: left;"
                        >
                            <AppTableActions
                                style="display: flex; justify-content: flex-start; width: 100%;"
                            >
                                <AppCrudActionButton action="view" title="View trip" @click="goToView(trip)" />
                                <AppCrudActionButton
                                    v-if="canRestore"
                                    action="restore"
                                    title="Restore trip"
                                    :disabled="restoringTripId === trip.id"
                                    @click="openRestoreConfirm(trip)"
                                />
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

        <TripRestoreConfirmModal
            :show="showRestoreConfirm"
            :summary="restoreConfirmSummary"
            :restoring="restoringTripId !== null"
            @cancel="closeRestoreConfirm"
            @confirm="confirmRestoreTrip"
        />
    </div>
</template>

<script setup>
import { computed, inject, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import { listAllCustomers } from '../../api/customers';
import {
    defaultTripListFilters,
    listTrips,
    restoreTrip,
    tripStatusMeta,
    tripTypeMeta,
    TRIP_STATUSES,
} from '../../api/trips';
import DashboardDateRangePicker from '../../components/dashboard/DashboardDateRangePicker.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import SearchablePicker from '../../components/ui/SearchablePicker.vue';
import { TRIP_LIST_DATE_PRESET_KEYS } from '../../utils/dateRangePresets';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import TripRestoreConfirmModal from '../../components/trips/TripRestoreConfirmModal.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { invalidateOperationalData } from '../../composables/useOperationalDataInvalidation';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatDate } from '../../utils/datetime';
import { buildTripRestoreConfirmViewModel } from '../../utils/tripRestoreConfirm';
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
const canRestore = computed(() => canInCompany('trips.restore'));
const canOpenTrip = computed(() => canView.value);
const showActions = computed(() => canOpenTrip.value || canRestore.value);
const tableColspan = computed(() => (showActions.value ? 7 : 6));

const { applyApiError } = useApiFormErrors();

const trips = ref([]);
const customers = ref([]);
const loading = ref(true);
const showRestoreConfirm = ref(false);
const restoreConfirmSummary = ref(null);
const tripPendingRestore = ref(null);
const restoringTripId = ref(null);
const tripStatuses = TRIP_STATUSES;
const tripDatePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const filters = reactive(defaultTripListFilters());

const { pagination, syncFromMeta } = useListPagination(10);

const defaultFilters = defaultTripListFilters();
const listReady = ref(false);
let skipNextRouteSync = false;

const tenantId = computed(
    () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
);

function currentListState() {
    return {
        from_date: filters.from_date,
        to_date: filters.to_date,
        search: filters.search.trim(),
        status: filters.status,
        trip_type: '',
        customer_id: filters.customer_id,
        broker_id: '',
        page: pagination.currentPage,
        per_page: pagination.perPage,
    };
}

function applyListState(state) {
    filters.from_date = state.from_date ?? defaultFilters.from_date;
    filters.to_date = state.to_date ?? defaultFilters.to_date;
    filters.search = state.search ?? '';
    filters.status = state.status ?? '';
    filters.trip_type = '';
    filters.customer_id = state.customer_id ?? '';
    filters.broker_id = '';
    pagination.currentPage = Number(state.page) > 0 ? Number(state.page) : 1;
    pagination.perPage = Number(state.per_page) > 0 ? Number(state.per_page) : 10;
}

function listStatesEqual(left, right) {
    return (
        left.from_date === right.from_date &&
        left.to_date === right.to_date &&
        left.search === right.search &&
        left.status === right.status &&
        String(left.customer_id) === String(right.customer_id) &&
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

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCompactMoney(value) {
    const amount = Number(value ?? 0);

    if (!Number.isFinite(amount)) {
        return '0';
    }

    if (amount === 0) {
        return '0';
    }

    return amount.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function tripProfit(trip) {
    const freight = Number(trip.total_freight_amount ?? 0);
    const expense = Number(trip.total_expense_amount ?? 0);
    const profit = Number(trip.profit_amount ?? freight - expense);

    if (Number.isFinite(profit)) {
        return profit;
    }

    return freight - expense;
}

function financialSummaryTitle(trip) {
    const profit = tripProfit(trip);

    return `Freight ₹${formatMoney(trip.total_freight_amount)} · Expense ₹${formatMoney(trip.total_expense_amount)} · Profit ₹${formatMoney(profit)}`;
}

function tripTypeInfo(type) {
    return tripTypeMeta(type);
}

function tripStatusInfo(status) {
    return tripStatusMeta(status);
}

function routeSummary(trip) {
    const legs = trip.route_legs ?? [];
    const points = [];

    if (legs[0]?.from_city) {
        points.push(String(legs[0].from_city).trim());
    }

    legs.forEach((leg) => {
        const city = String(leg?.to_city ?? '').trim();

        if (city) {
            points.push(city);
        }
    });

    const cleaned = points.filter(Boolean);

    if (cleaned.length === 0) {
        return {
            source: '—',
            destination: '—',
            middle: [],
        };
    }

    return {
        source: cleaned[0],
        destination: cleaned[cleaned.length - 1],
        middle: cleaned.length > 2 ? cleaned.slice(1, -1) : [],
    };
}

function routeHoverTitle(trip) {
    const summary = routeSummary(trip);

    if (summary.middle.length === 0) {
        return `${summary.source} → ${summary.destination}`;
    }

    return `${summary.source} → ${summary.destination}\nVia: ${summary.middle.join(' · ')}`;
}

function showRouteFlash() {
    const { flash: flashQuery, filters: filterQuery } = splitTripArchivedListRouteQuery(route.query);

    if (flashQuery.denied === '1') {
        toast.error('You do not have permission to view archived trips, or company context is missing.');
        router.replace({ query: buildTripArchivedListQuery(resolveTripArchivedListState(filterQuery, tenantId.value)) });
    }
}

function clearFilters() {
    clearTripArchivedListStateFromSession(tenantId.value);
    Object.assign(filters, defaultTripListFilters());
    pagination.currentPage = 1;
    pagination.perPage = 10;
    syncListStateToRoute();
    loadTrips(1);
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
            from_date: filters.from_date || undefined,
            to_date: filters.to_date || undefined,
            customer_id: filters.customer_id || undefined,
            status: filters.status || undefined,
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

function openRestoreConfirm(trip) {
    if (!canRestore.value || restoringTripId.value) {
        return;
    }

    tripPendingRestore.value = trip;
    restoreConfirmSummary.value = buildTripRestoreConfirmViewModel(trip);
    showRestoreConfirm.value = true;
}

function closeRestoreConfirm() {
    if (restoringTripId.value) {
        return;
    }

    showRestoreConfirm.value = false;
    restoreConfirmSummary.value = null;
    tripPendingRestore.value = null;
}

function restoreTripErrorMessage(error) {
    if (error?.response?.status === 403) {
        return 'You do not have permission to restore trips.';
    }

    const tripError = error?.response?.data?.errors?.trip?.[0];

    if (tripError) {
        return tripError;
    }

    return friendlyApiErrorMessage(error, 'Unable to restore trip.');
}

async function confirmRestoreTrip() {
    if (!tripPendingRestore.value || restoringTripId.value) {
        return;
    }

    const tripId = tripPendingRestore.value.id;
    restoringTripId.value = tripId;

    try {
        await restoreTrip(tripId);
        toast.success('Trip restored successfully.');
        showRestoreConfirm.value = false;
        restoreConfirmSummary.value = null;
        tripPendingRestore.value = null;
        invalidateOperationalData(['trips', 'dashboard', 'trip-reports']);
        await loadTrips(pagination.currentPage);
    } catch (error) {
        toast.error(restoreTripErrorMessage(error));
    } finally {
        restoringTripId.value = null;
    }
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

async function loadFilterLookups() {
    customers.value = await listAllCustomers();
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

    listReady.value = true;
});

onBeforeUnmount(() => {
    saveTripArchivedListStateToSession(tenantId.value, currentListState());
});
</script>

<style scoped>
/* Keep filters + Search/Reset on one row */
:deep(.app-index-collapsible-filters__inner) {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: flex-end !important;
    gap: 10px !important;
}

:deep(.app-index-filters-fields) {
    display: contents !important;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0 !important;
    margin-left: auto !important;
    align-self: flex-end !important;
    flex: 0 0 auto !important;
    flex-wrap: nowrap !important;
    gap: 0.5rem;
}

.filter-search-col {
    flex: 0 0 240px !important;
    width: 240px !important;
    max-width: 240px !important;
    min-width: 0 !important;
}

.filter-period-col {
    flex: 0 0 200px !important;
    width: 200px !important;
    max-width: 200px !important;
    min-width: 0 !important;
}

.filter-select-col {
    flex: 0 0 150px !important;
    width: 150px !important;
    max-width: 150px !important;
    min-width: 0 !important;
}

.filter-picker-col {
    flex: 0 0 270px !important;
    width: 270px !important;
    max-width: 270px !important;
    min-width: 0 !important;
}

.filter-picker-col :deep(.picker-row) {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    gap: 0.35rem;
}

.filter-picker-col :deep(.picker-main) {
    flex: 1 1 auto;
    min-width: 0;
    width: auto !important;
    max-width: none !important;
}

.filter-picker-col :deep(.picker-trigger) {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.filter-picker-col :deep(.picker-actions) {
    position: static;
    flex: 0 0 auto;
    align-self: center;
    padding-top: 0;
    z-index: 2;
    pointer-events: auto;
}

.filter-picker-col :deep(.picker-actions .picker-action-btn) {
    position: relative;
    z-index: 2;
    pointer-events: auto;
}

.filter-search-col :deep(.app-index-search),
.filter-period-col :deep(.dashboard-date-range),
.filter-select-col :deep(.app-select),
.filter-picker-col :deep(.searchable-picker) {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
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

.trips-financials-col {
    width: 1%;
    white-space: nowrap;
    min-width: 8.75rem;
}

.trips-period-col {
    width: 1%;
    white-space: nowrap;
    line-height: 1.3;
}

.trips-route-col {
    max-width: 14rem;
    min-width: 10rem;
    overflow: hidden;
    white-space: normal;
    vertical-align: middle;
}

.trips-route {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    max-width: 100%;
    min-width: 0;
}

.trips-route__main {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 100%;
    min-width: 0;
}

.trips-route__point {
    display: inline-flex;
    align-items: center;
    gap: 0.22rem;
    min-width: 0;
    max-width: 5.5rem;
    font-weight: 600;
    color: #334155;
}

.trips-route__point i {
    flex-shrink: 0;
    font-size: 0.62rem;
    line-height: 1;
}

.trips-route__point--from i {
    color: #64748b;
}

.trips-route__point--to i {
    color: #0d6efd;
}

.trips-route__name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.trips-route__arrow {
    flex-shrink: 0;
    font-size: 0.7rem;
    color: #94a3b8;
}

.trips-route__via-badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.1rem;
    padding: 0 0.3rem;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1;
}

.trips-route__via {
    display: none;
    align-items: baseline;
    gap: 0.35rem;
    min-width: 0;
    padding-left: 0.05rem;
    color: #64748b;
    font-size: 0.6875rem;
    line-height: 1.25;
}

.trips-route--has-via:hover .trips-route__via,
.trips-route--has-via:focus-within .trips-route__via {
    display: flex;
}

.trips-route__via-label {
    flex-shrink: 0;
    font-weight: 700;
    text-transform: none;
    letter-spacing: normal;
    font-size: 0.6rem;
    color: #94a3b8;
}

.trips-route__via-stops {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 600;
    color: #475569;
}

.trips-financials {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    font-size: 0.75rem;
    line-height: 1.25;
}

.trips-financials__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.trips-financials__label {
    color: #6c757d;
    font-size: 0.6875rem;
    font-weight: 600;
}

.trips-financials__label::after {
    content: ':';
}

.trips-financials__val {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
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
