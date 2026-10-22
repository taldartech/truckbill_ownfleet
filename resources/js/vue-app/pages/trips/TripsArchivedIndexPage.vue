<template>
    <div class="container mt-2 mt-md-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing archived trips.
        </div>

        <AppIndexListCard
            title="Archived Trips"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading archived trips…"
        >
            <template #toolbar>
                <RouterLink
                    :to="{ name: 'trips.index' }"
                    class="btn btn-sm btn-light border-0 bg-white shadow-sm text-dark d-inline-flex align-items-center fw-semibold py-1 px-2 rounded-2"
                    title="Back to Trip List"
                    aria-label="Back to Trip List"
                >
                    <svg class="me-1 flex-shrink-0" width="14" height="14" viewBox="0 0 16 16" fill="#000000" aria-hidden="true">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
                    </svg>
                    <span>Back<span class="d-none d-sm-inline"> to Trip List</span></span>
                </RouterLink>
            </template>

            <template #filters>
                <div class="d-flex d-md-none align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                    <span class="fw-bold text-uppercase text-dark small text-nowrap" style="letter-spacing: 0.05em; font-size: 0.8125rem; white-space: nowrap;">SEARCH &amp; FILTERS</span>
                    <button
                        type="button"
                        class="btn btn-sm rounded-pill border bg-white text-dark shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold"
                        style="font-size: 0.8125rem;"
                        :class="{ 'bg-light border-secondary': filtersOpen }"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>
                        <span>Filters</span>
                        <i
                            class="bi ms-1 text-muted"
                            :class="filtersOpen ? 'bi-chevron-up' : 'bi-chevron-down'"
                            style="font-size: 0.75rem;"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div v-show="filtersOpen" class="trips-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="trips-archived-filters-panel"
                        :loading="loading"
                        @search="loadTrips(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label small fw-semibold" for="trips_archived_search">Search</label>
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
                            <label class="form-label small fw-semibold">Status</label>
                            <AppSelect
                                v-model="filters.status"
                                :options="tripStatuses"
                                placeholder="All statuses"
                                search-placeholder="Search status…"
                                :disabled="loading"
                            />
                        </div>
                        <div class="filter-picker-col">
                            <label class="form-label small fw-semibold">Customer</label>
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
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Trip #</th>
                        <th>Truck / Driver</th>
                        <th class="text-nowrap" style="width: 1%;">Status</th>
                        <th class="text-nowrap" style="min-width: 6.5rem;">Trip Date</th>
                        <th style="min-width: 10rem;">Route</th>
                        <th style="min-width: 8rem;">Freight / Expense / Profit</th>
                        <th v-if="showActions" class="text-nowrap text-end pe-2 pe-md-3" style="width: 1%;">Actions</th>
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
                            <div class="d-flex flex-column align-items-start gap-1" style="min-width: 7.5rem;">
                                <RouterLink
                                    v-if="canOpenTrip"
                                    :to="{ name: 'trips.show', params: { id: trip.id } }"
                                    class="link-primary fw-semibold text-decoration-none small"
                                >
                                    {{ trip.trip_number || '—' }}
                                </RouterLink>
                                <strong v-else class="small">{{ trip.trip_number || '—' }}</strong>
                                <span
                                    class="badge rounded-pill d-inline-flex align-items-center gap-1 fw-semibold text-nowrap"
                                    :class="tripTypeInfo(trip.trip_type).badge"
                                    :title="tripTypeInfo(trip.trip_type).label"
                                    style="font-size: 0.6875rem;"
                                >
                                    <i class="bi" :class="tripTypeInfo(trip.trip_type).icon" aria-hidden="true"></i>
                                    <span class="d-none d-sm-inline">{{ tripTypeInfo(trip.trip_type).label }}</span>
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
                        <td class="text-nowrap">
                            <span class="badge" :class="tripStatusInfo(trip.status).badge">
                                {{ tripStatusInfo(trip.status).label }}
                            </span>
                        </td>
                        <td class="text-nowrap small">
                            <div>{{ formatDate(trip.start_at) }}</div>
                            <div class="text-muted">{{ formatDate(trip.end_at) }}</div>
                        </td>
                        <td class="small" style="min-width: 10rem;">
                            <div
                                class="d-flex flex-column gap-1 w-100"
                                :title="routeHoverTitle(trip)"
                            >
                                <div class="d-inline-flex align-items-center gap-1 mw-100 flex-wrap">
                                    <span class="d-inline-flex align-items-center gap-1 fw-semibold text-secondary text-truncate" style="max-width: 10rem;">
                                        <i class="bi bi-circle-fill text-secondary" style="font-size: 0.6rem;" aria-hidden="true"></i>
                                        <span class="text-truncate">{{ routeSummary(trip).source }}</span>
                                    </span>
                                    <i class="bi bi-arrow-right text-muted flex-shrink-0" style="font-size: 0.7rem;" aria-hidden="true"></i>
                                    <span class="d-inline-flex align-items-center gap-1 fw-semibold text-primary text-truncate" style="max-width: 10rem;">
                                        <i class="bi bi-geo-alt-fill text-primary" style="font-size: 0.65rem;" aria-hidden="true"></i>
                                        <span class="text-truncate">{{ routeSummary(trip).destination }}</span>
                                    </span>
                                    <span
                                        v-if="routeSummary(trip).middle.length > 0"
                                        class="badge rounded-pill bg-light text-secondary border flex-shrink-0"
                                        style="font-size: 0.65rem;"
                                    >
                                        +{{ routeSummary(trip).middle.length }}
                                    </span>
                                </div>
                                <div
                                    v-if="routeSummary(trip).middle.length > 0"
                                    class="d-flex align-items-center gap-1 small text-truncate"
                                    style="font-size: 0.6875rem;"
                                >
                                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.6rem;">Via</span>
                                    <span class="text-secondary fw-semibold text-truncate">{{ routeSummary(trip).middle.join(' · ') }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="small" style="min-width: 7.5rem;">
                            <div class="d-flex flex-column gap-1" :title="financialSummaryTitle(trip)">
                                <div class="d-flex align-items-baseline justify-content-between gap-2">
                                    <span class="text-muted fw-semibold" style="font-size: 0.6875rem;">Freight:</span>
                                    <span class="fw-semibold font-monospace" style="font-size: 0.75rem;">₹{{ formatCompactMoney(trip.total_freight_amount) }}</span>
                                </div>
                                <div class="d-flex align-items-baseline justify-content-between gap-2">
                                    <span class="text-muted fw-semibold" style="font-size: 0.6875rem;">Expense:</span>
                                    <span class="fw-semibold font-monospace" style="font-size: 0.75rem;">₹{{ formatCompactMoney(trip.total_expense_amount) }}</span>
                                </div>
                                <div class="d-flex align-items-baseline justify-content-between gap-2">
                                    <span class="text-muted fw-semibold" style="font-size: 0.6875rem;">Profit:</span>
                                    <span
                                        class="fw-semibold font-monospace"
                                        :class="tripProfit(trip) >= 0 ? 'text-success' : 'text-danger'"
                                        style="font-size: 0.75rem;"
                                    >
                                        ₹{{ formatCompactMoney(tripProfit(trip)) }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td
                            v-if="showActions"
                            class="text-nowrap text-end pe-2 pe-md-3"
                            style="width: 1%;"
                        >
                            <AppTableActions class="justify-content-end">
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
const filtersOpen = ref(false);
const listReady = ref(false);
let skipNextRouteSync = false;

const tenantId = computed(
    () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
);

const filtersActive = computed(() => {
    return (
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.search.trim() !== '' ||
        filters.customer_id !== '' ||
        filters.status !== ''
    );
});

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
/* Gradient Header matching requested design */
:deep(.trips-card-header--gradient) {
    background: linear-gradient(90deg, #10b981 0%, #06b6d4 50%, #0ea5e9 100%) !important;
    border-bottom: none !important;
    padding: 0.75rem 1.15rem !important;
    border-top-left-radius: inherit !important;
    border-top-right-radius: inherit !important;
}

:deep(.trips-card-header--gradient .card-title) {
    color: #ffffff !important;
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    letter-spacing: -0.01em;
    margin-bottom: 0 !important;
    white-space: nowrap !important;
}

:deep(.trips-card-header--gradient .app-index-card-toolbar) {
    width: auto !important;
    flex: 0 0 auto !important;
    margin-left: auto !important;
}

@media (min-width: 768px) {
    :deep(.trips-card-header--gradient) {
        background: #ffffff !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125) !important;
        padding: 0.75rem 1.25rem !important;
    }

    :deep(.trips-card-header--gradient .card-title) {
        color: #212529 !important;
        font-size: 1.25rem !important;
    }

    .trips-filters-content {
        display: block !important;
    }
}


.trips-filters-content {
    animation: fadeIn 0.18s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 991.98px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: flex-end !important;
        gap: 10px !important;
    }
}

@media (min-width: 992px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: flex-end !important;
        gap: 8px !important;
    }
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
    min-height: 38px !important;
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

/* â”€â”€ Tablet / Middle Screens (768px - 1199.98px) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .app-index-list-card :deep(.card-title) {
        font-size: 1.1rem !important;
    }

    .app-index-list-card :deep(.app-index-header-btn) {
        font-size: 0.8125rem !important;
        padding: 0.35rem 0.65rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        justify-content: flex-end !important;
        margin-top: 0.25rem !important;
        margin-left: 0 !important;
    }

    .app-index-list-card :deep(.table) {
        font-size: 0.78rem !important;
    }

    .app-index-list-card :deep(.table th),
    .app-index-list-card :deep(.table td) {
        padding: 0.45rem 0.5rem !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon) {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon i) {
        font-size: 0.75rem !important;
    }
}

/* â”€â”€ Mobile Devices (< 768px) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
@media (max-width: 767.98px) {
    .app-index-list-card :deep(.card-header),
    :deep(.trips-card-header--gradient) {
        padding: 0.5rem 0.75rem !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.4rem !important;
    }

    .app-index-list-card :deep(.card-title),
    :deep(.trips-card-header--gradient .card-title) {
        font-size: 0.92rem !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
        margin-bottom: 0 !important;
        line-height: 1.2 !important;
        letter-spacing: -0.01em;
    }

    :deep(.app-index-collapsible-filters) {
        padding: 0.65rem 0.75rem !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        gap: 8px !important;
    }

    :deep(.app-index-filters-fields > div),
    .filter-search-col,
    .filter-period-col,
    .filter-select-col,
    .filter-picker-col {
        flex: 0 0 calc(50% - 4px) !important;
        width: calc(50% - 4px) !important;
        max-width: calc(50% - 4px) !important;
        min-width: 0 !important;
    }

    /* Filter inputs compact & responsive text sizing on mobile */
    :deep(.app-index-collapsible-filters .form-label),
    :deep(.dashboard-date-range__label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    :deep(.app-index-collapsible-filters .form-control),
    :deep(.app-index-collapsible-filters .select-trigger),
    :deep(.app-index-collapsible-filters .picker-trigger),
    :deep(.app-index-collapsible-filters .dashboard-date-range__trigger) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder),
    :deep(.app-index-collapsible-filters .select-trigger-label),
    :deep(.app-index-collapsible-filters .picker-trigger-label),
    :deep(.app-index-collapsible-filters .dashboard-date-range__value) {
        font-size: 0.75rem !important;
    }

    :deep(.dashboard-date-range__icon) {
        font-size: 0.7rem !important;
        margin-right: 0.25rem !important;
    }

    :deep(.dashboard-date-range__chevron),
    :deep(.select-trigger-icon) {
        font-size: 0.65rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 8px !important;
        margin-top: 0.35rem !important;
        margin-left: 0 !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.75rem !important;
        height: 28px !important;
        min-height: 28px !important;
        padding: 0.15rem 0.5rem !important;
        line-height: 1 !important;
        border-radius: 6px !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn i) {
        font-size: 0.7rem !important;
        margin-right: 0.25rem !important;
    }

    /* Table micro adjustments on mobile */
    .app-index-list-card :deep(.table) {
        font-size: 0.72rem !important;
    }

    .app-index-list-card :deep(.table th) {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
    }

    .app-index-list-card :deep(.table td) {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.72rem !important;
    }

    .app-index-list-card :deep(.app-table-actions) {
        gap: 4px !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon) {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        border-radius: 0.25rem !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon i) {
        font-size: 0.6875rem !important;
    }

    /* Pagination responsive on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.4rem !important;
        padding: 0.5rem 0.6rem !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.72rem !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: 0 !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.2rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

/* Extra-Small Mobile Devices (< 380px) */
@media (max-width: 380px) {
    .app-index-list-card :deep(.card-title),
    :deep(.trips-card-header--gradient .card-title) {
        font-size: 0.84rem !important;
    }

    .trip-header-back-btn {
        padding: 0.22rem 0.45rem !important;
        font-size: 0.68rem !important;
    }
}
</style>
