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
                        placeholder="Trip #, truck registration, driver name…"
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
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Route</th>
                            <th>Freight</th>
                            <th>Expense</th>
                            <th>Profit</th>
                            <th v-if="showActions" class="app-index-actions-cell" style="width: 140px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="trips.length === 0">
                            <td :colspan="showActions ? 11 : 10" class="text-center text-muted py-4">
                                No trips found for selected filters.
                            </td>
                        </tr>
                        <tr v-for="trip in trips" :key="trip.id">
                            <td>
                                <RouterLink
                                    v-if="canOpenTrip"
                                    :to="{ name: 'trips.show', params: { id: trip.id } }"
                                    class="trip-number-link fw-semibold text-decoration-none"
                                >
                                    {{ trip.trip_number || '—' }}
                                </RouterLink>
                                <strong v-else>{{ trip.trip_number || '—' }}</strong>
                            </td>
                            <td>{{ trip.vehicle?.registration_number ?? '—' }}</td>
                            <td>{{ trip.driver?.name ?? '—' }}</td>
                            <td>
                                <TripInlineStatusActions
                                    :trip="trip"
                                    :can-manage="canManage"
                                    @updated="onTripStatusUpdated(trip, $event)"
                                />
                            </td>
                            <td>{{ formatDateTime(trip.start_at) }}</td>
                            <td>{{ formatDateTime(trip.end_at) }}</td>
                            <td class="small">{{ formatRoute(trip) }}</td>
                            <td>₹{{ formatMoney(trip.total_freight_amount) }}</td>
                            <td>₹{{ formatMoney(trip.total_expense_amount) }}</td>
                            <td :class="Number(trip.profit_amount) >= 0 ? 'text-success' : 'text-danger'">
                                ₹{{ formatMoney(trip.profit_amount) }}
                            </td>
                            <td v-if="showActions" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="view" title="View trip" @click="goToView(trip)" />
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
import { deleteTrip, defaultTripListFilters, isTerminalTripStatus, listTrips, resolveTripEditAccess, tripEditDisabledTitle, TRIP_STATUSES } from '../../api/trips';
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
import { formatDateTime } from '../../utils/datetime';

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
const tripDatePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const filters = reactive(defaultTripListFilters());

const { pagination, syncFromMeta } = useListPagination(15);

const defaultFilters = defaultTripListFilters();
const searchAutoLoadReady = ref(false);
let searchDebounceTimer = null;
let skipNextSearchWatch = false;

const searchIgnoresDateRange = computed(() => filters.search.trim() !== '');

const filtersActive = computed(() => {
    return (
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.search.trim() !== '' ||
        filters.customer_id !== '' ||
        filters.broker_id !== '' ||
        filters.status !== ''
    );
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function isTerminalTrip(trip) {
    return isTerminalTripStatus(trip.status);
}

function canEditTrip(trip) {
    return resolveTripEditAccess(trip.status, user.value).canEdit;
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
    const { created, updated, denied, ...rest } = route.query;

    if (created === '1') {
        flash.success = 'Trip created successfully.';
        toast.success(flash.success);
        router.replace({ query: rest });

        return;
    }

    if (updated === '1') {
        flash.success = 'Trip updated successfully.';
        toast.success(flash.success);
        router.replace({ query: rest });

        return;
    }

    if (denied === '1') {
        flash.error = 'You do not have permission to open that page, or company context is missing.';
        toast.error(flash.error);
        router.replace({ query: rest });
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

    const { create, ...rest } = route.query;
    router.replace({ query: rest });
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
    Object.assign(filters, defaultTripListFilters());
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
        });

        trips.value = result.data;
        syncFromMeta(result.meta, trips.value.length);
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
    router.push({ name: 'trips.show', params: { id: trip.id } });
}

function goToEdit(trip) {
    if (!canEditTrip(trip)) {
        return;
    }

    router.push({ name: 'trips.edit', params: { id: trip.id } });
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
    },
    { immediate: true },
);

watch(
    () => route.name,
    async (name, previousName) => {
        if (name !== 'trips.index' || !previousName || previousName === 'trips.index') {
            return;
        }

        await refreshUserContext();

        if (!needsCompanySelection.value) {
            await loadTrips(pagination.currentPage);
        }
    },
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
    const { status, from_date: fromDate, to_date: toDate } = route.query;

    if (typeof status === 'string' && status !== '') {
        filters.status = status;
    }

    if (typeof fromDate === 'string' && fromDate !== '') {
        filters.from_date = fromDate;
    }

    if (typeof toDate === 'string' && toDate !== '') {
        filters.to_date = toDate;
    }
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

    await loadTrips();
    searchAutoLoadReady.value = true;
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);
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
</style>
