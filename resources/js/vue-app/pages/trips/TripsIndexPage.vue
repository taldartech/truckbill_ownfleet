<template>
    <div class="container mt-2 mt-md-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before managing trips.
        </div>

        <AppIndexListCard
            title="Trip Management"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading trips…"
        >
            <template #toolbar>
                <div class="d-flex align-items-center gap-1 gap-sm-2 flex-nowrap">
                    <button
                        v-if="canCreate"
                        type="button"
                        class="btn bg-white rounded-3 shadow-sm border-0 d-inline-flex align-items-center justify-content-center p-0 flex-shrink-0 trip-header-square-btn trip-header-square-btn--create"
                        title="Create trip"
                        aria-label="Create trip"
                        @click="goToCreate"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="#10b981" class="bi bi-plus-circle" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                        </svg>
                    </button>
                    <button
                        v-if="canCreate"
                        type="button"
                        class="btn bg-white rounded-3 shadow-sm border-0 d-inline-flex align-items-center justify-content-center p-0 flex-shrink-0 trip-header-square-btn trip-header-square-btn--multi"
                        title="Create multi-point trip"
                        aria-label="Create multi-point trip"
                        @click="goToMultiPointCreate"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="#06b6d4" class="bi bi-link-45deg" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L7.34 10.992a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/>
                            <path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 4.009a2 2 0 0 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 0 0-4.243-4.243z"/>
                        </svg>
                    </button>
                </div>
            </template>

            <template #filters>
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                    <span class="fw-bold text-uppercase text-dark small" style="letter-spacing: 0.05em; font-size: 0.8125rem;">SEARCH &amp; FILTERS</span>
                    <button
                        type="button"
                        class="btn btn-sm rounded-pill border bg-white text-dark shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold"
                        style="font-size: 0.8125rem;"
                        :class="{ 'bg-light border-secondary': filtersOpen }"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>
                        <span>Filters</span>
                        <span v-if="filtersActive" class="badge bg-primary text-white rounded-pill ms-1" style="font-size: 0.65rem;">Active</span>
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
                        panel-id="trips-filters-panel"
                        :loading="loading"
                        @search="loadTrips(1)"
                        @reset="clearFilters"
                    >
                        <div class="col-6 col-md-6 col-xl-3 filter-search-col">
                            <label class="form-label small fw-semibold" for="trips_search">Search</label>
                            <AppIndexSearchInput
                                v-model="filters.search"
                                input-id="trips_search"
                                placeholder="Trip #, truck, driver"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter="loadTrips(1)"
                            />
                        </div>
                        <div class="col-6 col-md-6 col-xl-3 filter-period-col">
                            <DashboardDateRangePicker
                                v-model:from-date="filters.from_date"
                                v-model:to-date="filters.to_date"
                                label="Trip Date"
                                panel-align="start"
                                :preset-keys="tripDatePresetKeys"
                                :block-future-dates="false"
                            />
                        </div>
                        <div class="col-6 col-md-6 col-xl-2 filter-select-col">
                            <label class="form-label small fw-semibold">Status</label>
                            <AppSelect
                                v-model="filters.status"
                                :options="tripStatuses"
                                placeholder="All statuses"
                                search-placeholder="Search status…"
                                :disabled="loading"
                            />
                        </div>
                        <div class="col-6 col-md-6 col-xl-4 filter-picker-col">
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
                            No trips found for selected filters.
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
                            <TripInlineStatusActions
                                :trip="trip"
                                :can-manage="canEdit"
                                @updated="onTripStatusUpdated(trip, $event)"
                            />
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
                               

                                <AppCrudActionButton
                                    v-if="canManageExpenses && canManageTripExpensesFor(trip)"
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
} from '../../api/trips';
import TripArchiveConfirmModal from '../../components/trips/TripArchiveConfirmModal.vue';
import TripInlineStatusActions from '../../components/trips/TripInlineStatusActions.vue';
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
const canManageExpenses = computed(() => canInCompany('trips.expenses'));
const canArchive = computed(() => canInCompany('trips.archive') || canInCompany('trips.delete'));
const canCreateInvoices = computed(() => canInCompany('invoices.create'));
const tableColspan = computed(() => (showActions.value ? 7 : 6));
const canView = computed(() => canInCompany('trips.view'));
const canOpenTrip = computed(() => canView.value || canEdit.value || canManageExpenses.value);
const showActions = computed(() => canOpenTrip.value || canCreateInvoices.value || canManageExpenses.value);

const { applyApiError } = useApiFormErrors();

const trips = ref([]);
const customers = ref([]);
const loading = ref(true);
const showArchiveConfirm = ref(false);
const archiveConfirmSummary = ref(null);
const archivingTrip = ref(false);
const archiveFieldErrors = ref({});
const tripPendingArchive = ref(null);
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

    router.push({ name: 'trips.create' });

    return true;
}

function goToMultiPointCreate() {
    if (needsCompanySelection.value) {
        toast.warning('Select and enter a company before creating a trip.');

        return false;
    }

    if (!canCreate.value) {
        toast.error('You do not have permission to create trips.');

        return false;
    }

    router.push({ name: 'trips.multi-point.create' });

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

function clearFilters() {
    clearTripListStateFromSession(tenantId.value);
    Object.assign(filters, defaultTripListFilters());
    pagination.currentPage = 1;
    pagination.perPage = 10;
    syncListStateToRoute();
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
            from_date: filters.from_date || undefined,
            to_date: filters.to_date || undefined,
            customer_id: filters.customer_id || undefined,
            status: filters.status || undefined,
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

    if (String(trip.trip_type ?? '').toUpperCase() === 'MULTI_POINT') {
        router.push({ name: 'trips.multi-point.edit', params: { id: trip.id } });

        return;
    }

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

async function loadFilterLookups() {
    customers.value = await listAllCustomers();
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
});

onBeforeUnmount(() => {
    saveTripListStateToSession(tenantId.value, currentListState());
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

/* White square header buttons dimensions */
.trip-header-square-btn {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 11px;
    background-color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    border: none !important;
}

.trip-header-square-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.14);
}

.trip-header-square-btn:active {
    transform: translateY(0);
}

.trip-header-square-btn--create {
    color: #10b981 !important;
}

.trip-header-square-btn--create svg,
.trip-header-square-btn--create i,
.trip-header-square-btn--create .bi {
    color: #10b981 !important;
    fill: #10b981 !important;
}

.trip-header-square-btn--multi {
    color: #06b6d4 !important;
}

.trip-header-square-btn--multi svg,
.trip-header-square-btn--multi i,
.trip-header-square-btn--multi .bi {
    color: #06b6d4 !important;
    fill: #06b6d4 !important;
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

/* Filters container and picker integration */
:deep(.app-index-collapsible-filters__inner) {
    display: flex !important;
    flex-wrap: wrap !important;
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

/* ── Tablet / Middle Screens (768px - 1199.98px) ───────────────────── */
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

    .trip-header-square-btn {
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        border-radius: 10px !important;
    }

    .trip-header-square-btn svg {
        width: 20px !important;
        height: 20px !important;
    }

    .trip-header-square-btn i {
        font-size: 1.2rem !important;
    }
}

/* ── Mobile Devices (< 768px) ─────────────────────────────────────── */
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

    .app-index-list-card :deep(.app-index-card-toolbar),
    :deep(.trips-card-header--gradient .app-index-card-toolbar) {
        width: auto !important;
        flex: 0 0 auto !important;
        margin-left: auto !important;
        flex-wrap: nowrap !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.35rem !important;
    }

    .trip-header-square-btn {
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        border-radius: 9px !important;
    }

    .trip-header-square-btn svg {
        width: 19px !important;
        height: 19px !important;
    }

    .trip-header-square-btn i {
        font-size: 1.05rem !important;
        line-height: 1 !important;
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

/* ── Extra-Small Mobile Devices (< 380px) ─────────────────────────── */
@media (max-width: 380px) {
    .app-index-list-card :deep(.card-title),
    :deep(.trips-card-header--gradient .card-title) {
        font-size: 0.84rem !important;
    }

    .trip-header-square-btn {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        border-radius: 7px !important;
    }

    .trip-header-square-btn svg {
        width: 16px !important;
        height: 16px !important;
    }

    .trip-header-square-btn i {
        font-size: 0.95rem !important;
    }
}
</style>
