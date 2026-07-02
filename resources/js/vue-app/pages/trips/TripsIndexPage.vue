<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Trip Management">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="goToCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before managing trips.
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card mb-3 trips-top-search">
            <div class="card-body py-3">
                <label class="form-label mb-2" for="trips_search">Search</label>
                <div class="trips-top-search__row">
                    <AppIndexSearchInput
                        v-model="filters.search"
                        input-id="trips_search"
                        placeholder="Trip #, truck registration, primary or substitute driver…"
                        max-width="100%"
                        :disabled="loading"
                    />
                </div>
                <div v-if="searchIgnoresDateRange" class="form-text mb-0 mt-2">
                    Search looks across all trips and ignores the date range.
                </div>
            </div>
        </div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadTrips(1)" @clear="clearFilters">
            <div class="trips-filter-period">
                <DashboardDateRangePicker
                    v-model:from-date="filters.from_date"
                    v-model:to-date="filters.to_date"
                    label="Period"
                    panel-align="start"
                    :preset-keys="tripDatePresetKeys"
                    @change="onDateRangeChange"
                />
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="filters.status" class="form-select">
                    <option value="">All statuses</option>
                    <option v-for="s in tripStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Trip Type</label>
                <select v-model="filters.trip_type" class="form-select">
                    <option value="">All trip types</option>
                    <option v-for="t in tripTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
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
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading trips…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Trip #</th>
                            <th>Truck</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th class="trips-period-col">Period</th>
                            <th class="trips-route-col">Route</th>
                            <th class="trips-financials-col">Freight / Expense / Profit</th>
                            <th v-if="showActions" class="app-index-actions-cell" style="width: 180px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="trips.length === 0">
                            <td :colspan="showActions ? 8 : 7" class="text-center text-muted py-4">
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
                            <td>{{ trip.vehicle?.registration_number ?? '—' }}</td>
                            <td>
                                <div>{{ trip.driver?.name ?? '—' }}</div>
                                <div v-if="trip.substitute_driver?.name" class="small text-muted">
                                    Substitute: {{ trip.substitute_driver.name }}
                                </div>
                            </td>
                            <td>
                                <TripInlineStatusActions
                                    :trip="trip"
                                    :can-manage="canManage"
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
                                            v-if="profitDisplay(trip).value != null"
                                            class="trips-financials__val"
                                            :class="profitDisplay(trip).value >= 0 ? 'text-success' : 'text-danger'"
                                        >
                                            ₹{{ formatCompactMoney(profitDisplay(trip).value) }}
                                        </span>
                                        <span
                                            v-else
                                            class="trips-financials__val trips-financials__val--pending"
                                            :title="profitDisplay(trip).hint"
                                        >
                                            —
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td v-if="showActions" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="view" title="View trip" @click="goToView(trip)" />
                                    <AppCrudActionButton
                                        v-if="canManage && canManageTripExpensesFor(trip)"
                                        action="expenses"
                                        title="Trip expenses"
                                        @click="goToExpenses(trip)"
                                    />
                                    <AppCrudActionButton
                                        v-if="canManage"
                                        action="edit"
                                        :disabled="!canEditTrip(trip)"
                                        :title="tripEditTitle(trip)"
                                        @click="goToEdit(trip)"
                                    />
                                    <AppCrudActionButton
                                        v-if="canDelete"
                                        action="delete"
                                        @click="handleDelete(trip)"
                                    />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                    </table>
                </div>
            </div>
            <AppIndexPaginationFooter
                page-id="tripsIndex"
                :loading="loading"
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :per-page="pagination.perPage"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
                item-label="trips"
                :per-page-options="[15, 25, 50]"
                @page-change="loadTrips"
                @update:per-page="onPerPageUpdate"
            />
        </div>

        <TripTypeSelectModal
            :show="showTripTypeModal"
            @cancel="showTripTypeModal = false"
            @confirm="onTripTypeSelected"
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
    deleteTrip,
    defaultTripListFilters,
    isTerminalTripStatus,
    listTrips,
    canFullEditTrip,
    canManageTripExpenses,
    tripEditDisabledTitle,
    tripTypeMeta,
    TRIP_STATUSES,
    TRIP_TYPES,
} from '../../api/trips';
import TripInlineStatusActions from '../../components/trips/TripInlineStatusActions.vue';
import TripTypeSelectModal from '../../components/trips/TripTypeSelectModal.vue';
import DashboardDateRangePicker from '../../components/dashboard/DashboardDateRangePicker.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import SearchablePicker from '../../components/ui/SearchablePicker.vue';
import { TRIP_LIST_DATE_PRESET_KEYS } from '../../utils/dateRangePresets';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
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
const { canInCompany, canDeleteRecords, user } = usePermissions(currentUser);
const canManage = computed(() => canInCompany('trips.manage'));
const canDelete = computed(() => canManage.value && canDeleteRecords.value);
const canView = computed(() => canInCompany('trips.view'));
const canOpenTrip = computed(() => canView.value || canManage.value);
const showActions = computed(() => canOpenTrip.value);

const { applyApiError } = useApiFormErrors();

const trips = ref([]);
const customers = ref([]);
const brokers = ref([]);
const loading = ref(true);
const flash = reactive({ success: '', error: '' });
const showTripTypeModal = ref(false);
const tripStatuses = TRIP_STATUSES;
const tripTypes = TRIP_TYPES;
const tripDatePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const filters = reactive(defaultTripListFilters());

const { pagination, syncFromMeta } = useListPagination(15);

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
    pagination.perPage = Number(state.per_page) > 0 ? Number(state.per_page) : 15;
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

function profitDisplay(trip) {
    const expense = Number(trip.total_expense_amount ?? 0);
    const profit = Number(trip.profit_amount ?? 0);
    const status = String(trip.status ?? '').toUpperCase();

    if (expense <= 0 && ['CREATED', 'IN_PROGRESS'].includes(status)) {
        return {
            value: null,
            hint: 'Profit = Freight − Expense. Add trip expenses first.',
        };
    }

    return { value: profit, hint: null };
}

function financialSummaryTitle(trip) {
    const profit = profitDisplay(trip);

    if (profit.value == null) {
        return `Freight ₹${formatMoney(trip.total_freight_amount)} · Expense ₹${formatMoney(trip.total_expense_amount)} · Profit pending (add expenses)`;
    }

    return `Freight ₹${formatMoney(trip.total_freight_amount)} · Expense ₹${formatMoney(trip.total_expense_amount)} · Profit ₹${formatMoney(profit.value)}`;
}

function tripTypeInfo(type) {
    return tripTypeMeta(type);
}

function isTerminalTrip(trip) {
    return isTerminalTripStatus(trip.status);
}

function canEditTrip(trip) {
    return canFullEditTrip(trip.status, user.value);
}

function canManageTripExpensesFor(trip) {
    return canManageTripExpenses(trip.status, user.value);
}

function tripEditTitle(trip) {
    return tripEditDisabledTitle(trip.status, user.value);
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
        flash.success = 'Trip created successfully.';
        toast.success(flash.success);
        router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });

        return;
    }

    if (flashQuery.updated === '1') {
        flash.success = 'Trip updated successfully.';
        toast.success(flash.success);
        router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });

        return;
    }

    if (flashQuery.denied === '1') {
        flash.error = 'You do not have permission to open that page, or company context is missing.';
        toast.error(flash.error);
        router.replace({ query: buildTripListQuery(resolveTripListState(filterQuery, tenantId.value)) });
    }
}

function goToCreate() {
    if (needsCompanySelection.value) {
        flash.error = 'Select and enter a company before creating a trip.';
        toast.warning(flash.error);

        return false;
    }

    if (!canManage.value) {
        flash.error = 'You do not have permission to create trips.';
        toast.error(flash.error);

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
    pagination.perPage = 15;
    syncListStateToRoute();
    loadTrips(1);
}

function scheduleSearchLoad() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        loadTrips(1);
    }, 350);
}

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    loadTrips(1);
}

function onDateRangeChange() {
    loadTrips(1);
}

async function loadTrips(page = 1) {
    loading.value = true;
    flash.error = '';

    const search = filters.search.trim();

    try {
        const result = await listTrips({
            page,
            perPage: pagination.perPage,
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
            flash.error = friendlyApiErrorMessage(error, 'Failed to load trips.');
            toast.error(flash.error);
        }
    } finally {
        loading.value = false;
    }
}

function goToView(trip) {
    saveTripListStateToSession(tenantId.value, currentListState());
    router.push({ name: 'trips.show', params: { id: trip.id } });
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

async function handleDelete(trip) {
    if (!window.confirm(`Delete trip ${trip.trip_number || trip.id}?`)) {
        return;
    }

    try {
        await deleteTrip(trip.id);
        flash.success = 'Trip deleted.';
        await loadTrips(pagination.currentPage);
    } catch (error) {
        if (!applyApiError(error, 'Failed to delete trip.')) {
            flash.error = friendlyApiErrorMessage(error, 'Failed to delete trip.');
            toast.error(flash.error);
        }
    }
}

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
.trips-top-search {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.trips-top-search__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.trips-top-search__row :deep(.app-index-search) {
    flex: 1 1 16rem;
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
