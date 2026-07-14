<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before managing trips.
        </div>

        <AppIndexListCard title="Trip Management" :loading="loading" loading-text="Loading trips…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Create trip"
                    @click="goToCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="trips-filters-panel"
                :loading="loading"
                @search="loadTrips(1)"
                @reset="clearFilters"
            >
                <div class="trips-filter-search">
                    <label class="form-label" for="trips_search">
                        Search
                        <span v-if="searchIgnoresDateRange" class="text-muted fw-normal ms-1" style="font-size: 0.75rem;">
                            (ignores date range)
                        </span>
                    </label>
                    <AppIndexSearchInput
                        v-model="filters.search"
                        input-id="trips_search"
                        placeholder="Search trip #, truck, driver..."
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadTrips(1)"
                    />
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
                <div class="filter-select-col">
                    <label class="form-label">Trip Type</label>
                    <AppSelect
                        v-model="filters.trip_type"
                        :options="tripTypes"
                        placeholder="All trip types"
                        search-placeholder="Search trip type…"
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
                <div class="filter-picker-col">
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
                        <th>Truck / Driver</th>
                        <th class="trips-status-col">Status</th>
                        <th class="trips-period-col">Trip Date</th>
                        <th class="trips-route-col">Route</th>
                        <th class="trips-financials-col">Freight / Expense / Profit</th>
                        <th v-if="showActions" class="app-index-actions-cell text-end" style="width: 180px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="trips.length === 0" class="app-index-empty-row">
                        <td :colspan="tableColspan" class="text-center text-muted py-4">
                            No trips found for selected filters.
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
                            <TripInlineStatusActions
                                :trip="trip"
                                :can-manage="canEdit"
                                @updated="onTripStatusUpdated(trip, $event)"
                            />
                        </td>
                        <td class="trips-period-col small">
                            <div>{{ formatDate(trip.start_at) }}</div>
                            <div class="text-muted">{{ formatDate(trip.end_at) }}</div>
                        </td>
                        <td class="trips-route-col small" :title="formatRoute(trip)">
                            {{ formatRoute(trip) }}
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
                               

                                <AppCrudActionButton
                                    v-if="canEdit && canManageTripExpensesFor(trip)"
                                    action="expenses"
                                    title="Trip expenses"
                                    @click="goToExpenses(trip)"
                                />

                                <AppCrudActionButton
                                    v-if="canGenerateInvoiceFor(trip)"
                                    action="invoice"
                                    title="Generate invoice(s)"
                                    @click="goToGenerateInvoices(trip)"
                                />

                                <AppCrudActionButton
                                    v-if="canPrintBiltyFor(trip)"
                                    action="bilty"
                                    title="Print bilty"
                                    @click="goToPrintBilty(trip)"
                                />

                                <AppCrudActionButton
                                    v-if="canEdit && canEditTrip(trip)"
                                    action="edit"
                                    title="Edit trip"
                                    @click="goToEdit(trip)"
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
                    aria-label="Trips pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <TripTypeSelectModal
            :show="showTripTypeModal"
            @cancel="showTripTypeModal = false"
            @confirm="onTripTypeSelected"
        />

        <TripArchiveConfirmModal
            :show="showArchiveConfirm"
            :summary="archiveConfirmSummary"
            :archiving="archivingTrip"
            :field-errors="archiveFieldErrors"
            @cancel="closeArchiveConfirm"
            @confirm="confirmArchiveTrip"
        />
    </div>
</template>

<script setup>
import { computed, inject, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import { listAllBrokers } from '../../api/brokers';
import { listAllCustomers } from '../../api/customers';
import {
    archiveTrip,
    defaultTripListFilters,
    fetchTripArchiveImpact,
    listTrips,
    canFullEditTrip,
    canArchiveTrip,
    tripArchiveDisabledTitle,
    canManageTripExpenses,
    tripTypeMeta,
    TRIP_STATUSES,
    TRIP_TYPES,
} from '../../api/trips';
import TripArchiveConfirmModal from '../../components/trips/TripArchiveConfirmModal.vue';
import TripInlineStatusActions from '../../components/trips/TripInlineStatusActions.vue';
import TripTypeSelectModal from '../../components/trips/TripTypeSelectModal.vue';
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
import {
    invalidateOperationalData,
    useOperationalDataInvalidation,
} from '../../composables/useOperationalDataInvalidation';
import { buildTripArchiveConfirmViewModel } from '../../utils/tripArchiveConfirm';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatDate } from '../../utils/datetime';
import {
    buildTripListQuery,
    clearTripListStateFromSession,
    hasFilterQueryParams,
    loadTripListStateFromSession,
    resolveTripListState,
    saveTripListStateToSession,
    splitTripListRouteQuery,
} from '../../utils/tripListFilterState';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const currentUser = inject('currentUser', ref(null));
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany, user } = usePermissions(currentUser);
const canCreate = computed(() => canInCompany('trips.create'));
const canEdit = computed(() => canInCompany('trips.edit'));
const canArchive = computed(() => canInCompany('trips.archive') || canInCompany('trips.delete'));
const canCreateInvoices = computed(() => canInCompany('invoices.create'));
const tableColspan = computed(() => (showActions.value ? 8 : 7));
const canView = computed(() => canInCompany('trips.view'));
const canOpenTrip = computed(() => canView.value || canEdit.value);
const showActions = computed(() => canOpenTrip.value || canCreateInvoices.value);

const { applyApiError } = useApiFormErrors();

const trips = ref([]);
const customers = ref([]);
const brokers = ref([]);
const loading = ref(true);
const showTripTypeModal = ref(false);
const showArchiveConfirm = ref(false);
const archiveConfirmSummary = ref(null);
const archivingTrip = ref(false);
const archiveFieldErrors = ref({});
const tripPendingArchive = ref(null);
const tripStatuses = TRIP_STATUSES;
const tripTypes = TRIP_TYPES;
const tripDatePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const filters = reactive(defaultTripListFilters());

const { pagination, syncFromMeta } = useListPagination(10);

const defaultFilters = defaultTripListFilters();
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

    saveTripListStateToSession(tenantId.value, state);

    const nextQuery = {
        ...buildTripListQuery(state),
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

function tripEditOptions(trip) {
    return { has_invoices: Boolean(trip?.has_invoices) };
}

function canEditTrip(trip) {
    return canFullEditTrip(trip.status, user.value, tripEditOptions(trip));
}

function canArchiveTripRow(trip) {
    return canArchive.value && canArchiveTrip(trip.status, user.value);
}

function tripArchiveTitle(trip) {
    return tripArchiveDisabledTitle(trip.status, user.value);
}

function canManageTripExpensesFor(trip) {
    return canManageTripExpenses(trip.status, user.value, tripEditOptions(trip));
}

function onTripStatusUpdated(trip, updated) {
    const index = trips.value.findIndex((item) => item.id === trip.id);

    if (index !== -1) {
        trips.value[index] = { ...trips.value[index], ...updated };
    }
}

function formatRoute(trip) {
    const legs = trip.route_legs ?? [];

    if (legs.length === 0) {
        return '—';
    }

    const points = [];

    if (legs[0]?.from_city) {
        points.push(legs[0].from_city);
    }

    legs.forEach((leg) => {
        if (leg.to_city) {
            points.push(leg.to_city);
        }
    });

    return points.length > 0 ? points.join(' → ') : '—';
}

function showRouteFlash() {
    const { flash: flashQuery, filters: filterQuery } = splitTripListRouteQuery(route.query);

    if (flashQuery.created === '1') {
        toast.success('Trip created successfully.');
        router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });

        return;
    }

    if (flashQuery.updated === '1') {
        toast.success('Trip updated successfully.');
        router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });

        return;
    }

    if (flashQuery.denied === '1') {
        toast.error('You do not have permission to open that page, or company context is missing.');
        router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });
    }
}

function goToCreate() {
    if (needsCompanySelection.value) {
        toast.warning('Select and enter a company before creating a trip.');

        return false;
    }

    if (!canCreate.value) {
        toast.error('You do not have permission to create trips.');

        return false;
    }

    showTripTypeModal.value = true;

    return true;
}

function openCreateModalFromRoute() {
    if (route.query.create !== '1') {
        return;
    }

    const { filters: filterQuery } = splitTripListRouteQuery(route.query);
    router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });
    goToCreate();
}

function onTripTypeSelected(tripType) {
    showTripTypeModal.value = false;

    const typeQuery = tripType === 'ONE_WAY' ? 'one_way' : 'round_trip';

    router.push({ name: 'trips.create', query: { type: typeQuery } });
}

function clearFilters() {
    clearTimeout(searchDebounceTimer);
    skipNextSearchWatch = true;
    clearTripListStateFromSession(tenantId.value);
    Object.assign(filters, defaultTripListFilters());
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
            archived: 'active',
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
        if (!applyApiError(error, 'Failed to load trips.')) {
            toast.error(friendlyApiErrorMessage(error, 'Failed to load trips.'));
        }
    } finally {
        loading.value = false;
    }
}

function goToView(trip) {
    saveTripListStateToSession(tenantId.value, currentListState());
    router.push({ name: 'trips.show', params: { id: trip.id } });
}

function canGenerateInvoiceFor(trip) {
    return canCreateInvoices.value && Boolean(trip?.can_generate_invoices);
}

function canPrintBiltyFor(trip) {
    if (!canView.value || trip?.is_archived) {
        return false;
    }

    return (trip?.route_legs ?? []).some(
        (leg) => Boolean(leg.customer_id) && Number(leg.weight_ton ?? 0) > 0,
    );
}

function goToGenerateInvoices(trip) {
    if (!canGenerateInvoiceFor(trip)) {
        return;
    }

    saveTripListStateToSession(tenantId.value, currentListState());
    router.push({
        name: 'trips.show',
        params: { id: trip.id },
        query: { generateInvoices: '1' },
    });
}

function goToPrintBilty(trip) {
    if (!canPrintBiltyFor(trip)) {
        return;
    }

    saveTripListStateToSession(tenantId.value, currentListState());
    router.push({
        name: 'trips.show',
        params: { id: trip.id },
        query: { printBilty: '1' },
    });
}

function goToEdit(trip) {
    if (!canEditTrip(trip)) {
        return;
    }

    saveTripListStateToSession(tenantId.value, currentListState());
    router.push({ name: 'trips.edit', params: { id: trip.id } });
}

function goToExpenses(trip) {
    if (!canManageTripExpensesFor(trip)) {
        return;
    }

    saveTripListStateToSession(tenantId.value, currentListState());
    router.push({ name: 'trips.expenses', params: { id: trip.id } });
}

async function handleArchive(trip) {
    if (!canArchiveTripRow(trip)) {
        toast.error(tripArchiveDisabledTitle(trip.status, user.value));

        return;
    }

    archiveFieldErrors.value = {};
    tripPendingArchive.value = trip;

    try {
        const impact = await fetchTripArchiveImpact(trip.id);
        archiveConfirmSummary.value = buildTripArchiveConfirmViewModel(impact);
        showArchiveConfirm.value = true;
    } catch (error) {
        toast.error(archiveTripErrorMessage(error));
    }
}

function closeArchiveConfirm() {
    if (archivingTrip.value) {
        return;
    }

    showArchiveConfirm.value = false;
    archiveConfirmSummary.value = null;
    tripPendingArchive.value = null;
    archiveFieldErrors.value = {};
}

function archiveTripErrorMessage(error) {
    if (error?.response?.status === 403) {
        return 'You do not have permission to archive trips.';
    }

    const tripError = error?.response?.data?.errors?.trip?.[0];

    if (tripError) {
        return tripError;
    }

    return friendlyApiErrorMessage(error, 'Unable to archive trip.');
}

async function confirmArchiveTrip({ archive_reason: archiveReason }) {
    if (!tripPendingArchive.value || archivingTrip.value) {
        return;
    }

    archivingTrip.value = true;
    archiveFieldErrors.value = {};

    try {
        await archiveTrip(tripPendingArchive.value.id, { archive_reason: archiveReason });
        toast.success('Trip archived successfully.');
        showArchiveConfirm.value = false;
        archiveConfirmSummary.value = null;
        tripPendingArchive.value = null;
        archiveFieldErrors.value = {};
        invalidateOperationalData(['trips', 'dashboard', 'trip-reports']);
        await loadTrips(pagination.currentPage);
    } catch (error) {
        if (error?.response?.status === 422 && error?.response?.data?.errors) {
            archiveFieldErrors.value = error.response.data.errors;
        } else {
            toast.error(archiveTripErrorMessage(error));
        }
    } finally {
        archivingTrip.value = false;
    }
}

useOperationalDataInvalidation('trips', () => {
    if (listReady.value) {
        loadTrips(pagination.currentPage);
    }
});

watch(
    () => route.query,
    () => {
        showRouteFlash();
        openCreateModalFromRoute();

        if (route.name !== 'trips.index' || skipNextRouteSync || !listReady.value) {
            return;
        }

        const nextState = resolveTripListState(route.query, tenantId.value);
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
    const nextState = resolveTripListState(route.query, tenantId.value);

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

    if (!hasFilterQueryParams(route.query) && loadTripListStateFromSession(tenantId.value)) {
        syncListStateToRoute();
    }

    listReady.value = true;
    searchAutoLoadReady.value = true;
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
    saveTripListStateToSession(tenantId.value, currentListState());
});
</script>

<style scoped>
/* Custom filters single/multi row layout */
:deep(.app-index-collapsible-filters__inner) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

:deep(.app-index-filters-fields) {
    display: contents;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
    margin-left: auto;
}

.trips-filter-search {
    flex: 0 0 320px;
    width: 320px;
}

.trips-filter-search :deep(.app-index-search) {
    max-width: none;
}

.trips-filter-period {
    flex: 0 0 260px;
    width: 260px;
}

.filter-select-col {
    flex: 0 0 180px;
    width: 180px;
}

.filter-picker-col {
    flex: 0 0 200px;
    width: 200px;
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
    max-width: 11rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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

.trips-financials__val--pending {
    color: #adb5bd;
    font-weight: 500;
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
