<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing due services.
        </div>

        <AppIndexListCard title="Due Services" :loading="loading" loading-text="Loading due services…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="maintenance-due-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add maintenance"
                    @click="goToCreate"
                >
                    Add Maintenance
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="maintenance-due-filters-panel"
                :loading="loading"
                @search="applyFilters"
                @reset="clearFilters"
            >
                <div class="maintenance-filter-period">
                    <DashboardDateRangePicker
                        v-model:from-date="filters.from_date"
                        v-model:to-date="filters.to_date"
                        label="Next Due Date"
                        panel-align="start"
                        :preset-keys="datePresetKeys"
                        :max-months="MAX_LIST_DATE_RANGE_MONTHS"
                        :block-future-dates="false"
                        @change="onDateRangeChange"
                    />
                </div>
                <div class="maintenance-filter-search">
                    <label class="form-label" :for="`${pageId}_search`">Search</label>
                    <AppIndexSearchInput
                        v-model="filters.search"
                        :input-id="`${pageId}_search`"
                        placeholder="Truck, service type, provider…"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter.prevent="applyFilters"
                    />
                </div>
                <div>
                    <label class="form-label" :for="`${pageId}_vehicle`">Truck</label>
                    <VehicleSelect
                        :input-id="`${pageId}_vehicle`"
                        v-model="filters.vehicle_id"
                        :vehicles="vehicles"
                        placeholder="All trucks"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label" :for="`${pageId}_service_type`">Service Type</label>
                    <MaintenanceServiceTypeSelect
                        :input-id="`${pageId}_service_type`"
                        v-model="filters.maintenance_service_type_id"
                        :service-types="serviceTypes"
                        placeholder="All types"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label">Overdue Type</label>
                    <AppSelect
                        v-model="filters.overdue_reason"
                        :options="overdueReasonOptions"
                        placeholder="All overdue types"
                        search-placeholder="Search type…"
                        :disabled="loading"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Truck</th>
                        <th>Last Service</th>
                        <th>Service Type</th>
                        <th class="text-end">Current KM</th>
                        <th>Next Due KM</th>
                        <th>Next Due Date</th>
                        <th>Overdue</th>
                        <th v-if="hasRowActions" class="app-index-actions-cell" style="width: 140px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="records.length === 0" class="app-index-empty-row">
                        <td :colspan="hasRowActions ? 9 : 8" class="text-center text-muted py-4">
                            No trucks are due for service with selected filters.
                        </td>
                    </tr>
                    <tr v-for="(record, index) in records" :key="record.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <RouterLink
                                v-if="record.vehicle?.id"
                                :to="{ name: 'trucks.show', params: { vehicleId: record.vehicle.id } }"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ vehicleLabel(record) }}
                            </RouterLink>
                            <span v-else>{{ vehicleLabel(record) }}</span>
                        </td>
                        <td>{{ formatDate(record.service_date) }}</td>
                        <td>{{ record.service_type_label ?? record.service_type ?? '—' }}</td>
                        <td class="text-end">{{ formatOdometer(record.vehicle?.odometer_km) }}</td>
                        <td>
                            <span
                                v-if="record.next_service_due_km"
                                class="badge"
                                :class="isKmOverdue(record) ? 'bg-warning text-dark' : 'bg-light text-dark border'"
                            >
                                {{ formatOdometer(record.next_service_due_km) }} km
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td>
                            <span
                                v-if="record.next_service_due_date"
                                class="badge"
                                :class="isDateOverdue(record) ? 'bg-danger' : 'bg-light text-dark border'"
                            >
                                {{ formatDate(record.next_service_due_date) }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td>
                            <span class="badge bg-danger">{{ record.overdue_reason_label ?? '—' }}</span>
                        </td>
                        <td v-if="hasRowActions" class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canView"
                                    action="view"
                                    title="View record"
                                    @click="goToView(record)"
                                />
                                <AppCrudActionButton
                                    v-if="canCreate"
                                    action="add"
                                    title="Add new service"
                                    @click="goToCreateForTruck(record)"
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
                    item-label="services"
                    aria-label="Due services pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref, unref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import {
    defaultMaintenanceDueFilters,
    listDueVehicleMaintenances,
    MAINTENANCE_OVERDUE_REASON_FILTERS,
} from '../../api/vehicleMaintenances';
import MaintenanceServiceTypeSelect from '../../components/ui/MaintenanceServiceTypeSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import DashboardDateRangePicker from '../../components/dashboard/DashboardDateRangePicker.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import {
    dateRangeExceedsMonths,
    dateRangeMonthsError,
    MAX_LIST_DATE_RANGE_MONTHS,
    TRIP_LIST_DATE_PRESET_KEYS,
} from '../../utils/dateRangePresets';
import { formatDate } from '../../utils/datetime';
import {
    isMaintenanceDateOverdue,
    isMaintenanceKmOverdue,
} from '../../utils/maintenanceDueStatus';
import {
    resolveMaintenanceDueListState,
    saveMaintenanceDueListStateToSession,
} from '../../utils/maintenanceDueListFilterState';
import { setMaintenanceListReturnSource } from '../../utils/maintenanceRecordsListFilterState';

const pageId = 'maintenanceDueIndex';
const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const injectedUser = inject('currentUser', null);
const currentUser = computed(() => unref(injectedUser) ?? window.__AUTH_USER__ ?? null);
const tenantId = computed(
    () => currentUser.value?.effective_tenant_id ?? currentUser.value?.company_id ?? null,
);

const canView = computed(() => canInCompany('maintenance.view'));
const canCreate = computed(() => canInCompany('maintenance.create'));
const hasRowActions = computed(() => canView.value || canCreate.value);

const serviceTypes = ref([]);
const vehicles = ref([]);
const allRecords = ref([]);
const loading = ref(true);
const filtersOpen = ref(false);

const filters = reactive(defaultMaintenanceDueFilters());
const defaultFilters = defaultMaintenanceDueFilters();
const overdueReasonOptions = MAINTENANCE_OVERDUE_REASON_FILTERS;
const datePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const records = computed(() => {
    const start = (pagination.currentPage - 1) * pagination.perPage;

    return allRecords.value.slice(start, start + pagination.perPage);
});

const filtersActive = computed(
    () =>
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.search !== '' ||
        filters.vehicle_id !== '' ||
        filters.maintenance_service_type_id !== '' ||
        filters.overdue_reason !== '',
);

function currentListState() {
    return {
        from_date: filters.from_date,
        to_date: filters.to_date,
        search: filters.search,
        vehicle_id: filters.vehicle_id,
        maintenance_service_type_id: filters.maintenance_service_type_id,
        overdue_reason: filters.overdue_reason,
        page: pagination.currentPage,
        per_page: pagination.perPage,
    };
}

function persistListState() {
    setMaintenanceListReturnSource('due');
    saveMaintenanceDueListStateToSession(tenantId.value, currentListState());
}

function applyListState(state) {
    filters.from_date = state.from_date ?? '';
    filters.to_date = state.to_date ?? '';
    filters.search = state.search ?? '';
    filters.vehicle_id = state.vehicle_id ?? '';
    filters.maintenance_service_type_id = state.maintenance_service_type_id
        ? String(state.maintenance_service_type_id)
        : '';
    filters.overdue_reason = state.overdue_reason ?? '';
    pagination.currentPage = Number(state.page) > 0 ? Number(state.page) : 1;
    pagination.perPage = Number(state.per_page) > 0 ? Number(state.per_page) : 10;
}

function readQueryValue(key) {
    const value = route.query[key];

    if (typeof value === 'string') {
        return value;
    }

    if (Array.isArray(value) && typeof value[0] === 'string') {
        return value[0];
    }

    return '';
}

function applyStateFromRouteOrSession() {
    const hasQueryFilters = [
        'from_date',
        'to_date',
        'search',
        'vehicle_id',
        'maintenance_service_type_id',
        'overdue_reason',
    ].some((key) => readQueryValue(key) !== '');

    if (hasQueryFilters) {
        applyListState({
            from_date: readQueryValue('from_date'),
            to_date: readQueryValue('to_date'),
            search: readQueryValue('search'),
            vehicle_id: readQueryValue('vehicle_id'),
            maintenance_service_type_id: readQueryValue('maintenance_service_type_id'),
            overdue_reason: readQueryValue('overdue_reason'),
            page: readQueryValue('page') || 1,
            per_page: pagination.perPage,
        });

        return;
    }

    applyListState(resolveMaintenanceDueListState(tenantId.value));
}

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function vehicleLabel(record) {
    const label = record.vehicle?.registration_number ?? '—';

    if (record.vehicle?.is_active === false) {
        return `${label} (Inactive)`;
    }

    return label;
}

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN');
}

function isKmOverdue(record) {
    return isMaintenanceKmOverdue(record);
}

function isDateOverdue(record) {
    return isMaintenanceDateOverdue(record);
}

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

function syncClientPagination(total, page = 1) {
    const perPage = pagination.perPage;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const safePage = Math.min(Math.max(page, 1), lastPage);

    syncFromMeta(
        {
            current_page: safePage,
            last_page: lastPage,
            per_page: perPage,
            total,
            from: total > 0 ? (safePage - 1) * perPage + 1 : 0,
            to: total > 0 ? Math.min(safePage * perPage, total) : 0,
        },
        Math.min(perPage, Math.max(total - (safePage - 1) * perPage, 0)),
    );
}

function applyFilters() {
    const hasFrom = Boolean(filters.from_date);
    const hasTo = Boolean(filters.to_date);

    if (hasFrom !== hasTo) {
        toast.error('Select both From and To dates for the Next Due Date filter.');

        return;
    }

    if (hasFrom && hasTo && dateRangeExceedsMonths(filters.from_date, filters.to_date, MAX_LIST_DATE_RANGE_MONTHS)) {
        toast.error(dateRangeMonthsError(MAX_LIST_DATE_RANGE_MONTHS));

        return;
    }

    pagination.currentPage = 1;
    persistListState();
    loadRecords(1);
}

function onDateRangeChange(range = {}) {
    if (range.from_date != null) {
        filters.from_date = range.from_date;
    }

    if (range.to_date != null) {
        filters.to_date = range.to_date;
    }

    applyFilters();
}

function clearFilters() {
    Object.assign(filters, defaultMaintenanceDueFilters());
    pagination.currentPage = 1;
    persistListState();
    applyFilters();
}

async function loadRecords(page = 1) {
    if (!canView.value) {
        allRecords.value = [];
        resetPagination();
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        allRecords.value = await listDueVehicleMaintenances({
            search: filters.search.trim(),
            vehicleId: filters.vehicle_id,
            maintenanceServiceTypeId: filters.maintenance_service_type_id,
            overdueReason: filters.overdue_reason,
            from: filters.from_date,
            to: filters.to_date,
        });
        syncClientPagination(allRecords.value.length, page);
        persistListState();
    } catch {
        toast.error('Could not load due services.');
        allRecords.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    syncClientPagination(allRecords.value.length, page);
    persistListState();
}

function goToCreate() {
    persistListState();
    router.push({ name: 'maintenance.create' });
}

function goToCreateForTruck(record) {
    if (!record.vehicle_id) {
        return;
    }

    persistListState();

    const query = { vehicle_id: record.vehicle_id };

    if (record.maintenance_service_type_id) {
        query.maintenance_service_type_id = String(record.maintenance_service_type_id);
    }

    router.push({ name: 'maintenance.create', query });
}

function goToView(record) {
    persistListState();
    router.push({ name: 'maintenance.show', params: { id: record.id } });
}

onMounted(async () => {
    setMaintenanceListReturnSource('due');
    applyStateFromRouteOrSession();

    if (route.query.created === '1') {
        toast.success('Maintenance record created.');
    } else if (route.query.updated === '1') {
        toast.success('Maintenance record updated.');
    } else if (route.query.deleted === '1') {
        toast.success('Maintenance record deleted.');
    }

    if (filtersActive.value) {
        filtersOpen.value = true;
    }

    try {
        const [vehicleList, typeList] = await Promise.all([listAllVehicles(), listMaintenanceServiceTypes()]);

        vehicles.value = vehicleList;
        serviceTypes.value = typeList;
    } catch {
        vehicles.value = [];
        serviceTypes.value = [];
    }

    if (canView.value) {
        await loadRecords(pagination.currentPage);
    } else {
        loading.value = false;
    }
});
</script>

<style scoped>
.maintenance-filter-search :deep(.app-index-search) {
    width: 100%;
    max-width: none;
}

.maintenance-filter-search :deep(.app-index-search > .form-control),
.maintenance-filter-search :deep(.app-index-search > .input-group-text) {
    min-height: 38px;
}
</style>
