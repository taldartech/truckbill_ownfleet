<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing due services.
        </div>

        <AppIndexListCard title="Due Services" :loading="loading" loading-text="Loading due services…">
            <template #toolbar>
                <button
                    type="button"
                    class="btn btn-sm bg-white shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold maintenance-header-btn maintenance-header-btn--filter"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    <svg class="filter-slider-icon me-1" width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM9.05 3a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM2.05 8a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1H6.95a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h1.05zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-2.45 1a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05z"/>
                    </svg>
                    <span class="filter-btn-text">Filters</span>
                    <span v-if="filtersActive" class="badge bg-success text-white rounded-pill ms-1" style="font-size: 0.65rem;">Active</span>
                    <svg class="filter-chevron-icon ms-1" width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path v-if="filtersOpen" fill-rule="evenodd" d="M7.646 4.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1-.708.708L8 5.707l-5.646 5.647a.5.5 0 0 1-.708-.708l6-6z"/>
                        <path v-else fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    class="maintenance-header-btn maintenance-header-btn--add"
                    aria-label="Add maintenance"
                    @click="goToCreate"
                >
                    <span><span class="d-none d-sm-inline">Add </span>Maintenance</span>
                </AppIndexHeaderButton>
            </template>

            <template #filters>
                <div v-show="filtersOpen" class="maintenance-due-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="maintenance-due-filters-panel"
                        :loading="loading"
                        @search="applyFilters"
                        @reset="clearFilters"
                    >
                        <div class="filter-period-col">
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
                        <div class="filter-search-col">
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
                        <div class="filter-select-col filter-select-col--truck">
                            <label class="form-label" :for="`${pageId}_vehicle`">Truck</label>
                            <VehicleSelect
                                :input-id="`${pageId}_vehicle`"
                                v-model="filters.vehicle_id"
                                :vehicles="vehicles"
                                placeholder="All trucks"
                                :disabled="loading"
                            />
                        </div>
                        <div class="filter-select-col filter-select-col--service">
                            <label class="form-label" :for="`${pageId}_service_type`">Service Type</label>
                            <MaintenanceServiceTypeSelect
                                :input-id="`${pageId}_service_type`"
                                v-model="filters.maintenance_service_type_id"
                                :service-types="serviceTypes"
                                placeholder="All types"
                                :disabled="loading"
                            />
                        </div>
                        <div class="filter-select-col filter-select-col--overdue">
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
                </div>
            </template>

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
                                    action="service"
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

const filters = reactive(defaultMaintenanceDueFilters());
const defaultFilters = defaultMaintenanceDueFilters();
const overdueReasonOptions = MAINTENANCE_OVERDUE_REASON_FILTERS;
const datePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersOpen = ref(true);

const filtersActive = computed(
    () =>
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.search !== '' ||
        filters.vehicle_id !== '' ||
        filters.maintenance_service_type_id !== '' ||
        filters.overdue_reason !== '',
);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

const records = computed(() => {
    const start = (pagination.currentPage - 1) * pagination.perPage;

    return allRecords.value.slice(start, start + pagination.perPage);
});

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
/* ── Desktop & Base Filter Layout (>= 768px) ───────────────────────── */
@media (min-width: 768px) {
    .maintenance-due-filters-content {
        display: block !important;
    }
}

.maintenance-header-btn--filter,
.maintenance-header-btn--filter:hover,
.maintenance-header-btn--filter:focus,
.maintenance-header-btn--filter:active {
    border-radius: 8px !important;
    border: 1px solid #ffffff !important;
    background-color: #ffffff !important;
    background: #ffffff !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
    color: var(--brand-primary, #2fb36f) !important;
    font-size: 0.8125rem !important;
    font-weight: 600 !important;
    height: 32px !important;
    padding: 0.25rem 0.75rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.35rem !important;
}

.maintenance-header-btn--filter .filter-btn-text,
.maintenance-header-btn--filter .filter-slider-icon,
.maintenance-header-btn--filter .filter-chevron-icon,
.maintenance-header-btn--filter svg,
.maintenance-header-btn--filter span {
    color: var(--brand-primary, #2fb36f) !important;
    fill: currentColor !important;
}

/* Match the trip-list filter bar on desktop (>= 992px): all controls and actions share one row */
@media (min-width: 992px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: flex-end !important;
        gap: 7px !important;
        padding: 0.75rem !important;
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
        gap: 0.35rem !important;
    }

    .filter-period-col {
        flex: 0 0 150px !important;
        width: 150px !important;
        min-width: 0 !important;
    }

    .filter-search-col {
        flex: 1 1 180px !important;
        width: 180px !important;
        min-width: 150px !important;
        max-width: 210px !important;
    }

    .filter-select-col {
        flex: 0 1 125px !important;
        width: 125px !important;
        min-width: 105px !important;
    }

    .filter-select-col--truck {
        flex-basis: 110px !important;
        width: 110px !important;
    }

    .filter-select-col--service {
        flex-basis: 125px !important;
        width: 125px !important;
    }

    .filter-select-col--overdue {
        flex-basis: 135px !important;
        width: 135px !important;
    }
}

/* Tablet view (768px - 991.98px) */
@media (min-width: 768px) and (max-width: 991.98px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
        padding: 0.75rem !important;
    }

    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    .filter-period-col,
    .filter-search-col,
    .filter-select-col {
        flex: 0 0 calc(50% - 5px) !important;
        width: calc(50% - 5px) !important;
        min-width: 0 !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        justify-content: flex-end !important;
        margin-top: 4px !important;
    }
}

.filter-search-col :deep(.app-index-search),
.filter-period-col :deep(.dashboard-date-range),
.filter-select-col :deep(.app-select),
.filter-select-col :deep(.vehicle-select),
.filter-select-col :deep(.maintenance-service-type-select),
.filter-select-col :deep(.searchable-select) {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.filter-period-col :deep(.dashboard-date-range__label),
.filter-search-col > .form-label,
.filter-select-col > .form-label {
    margin-bottom: 0.25rem !important;
    font-size: 0.72rem !important;
    font-weight: 600 !important;
    white-space: nowrap;
}

.filter-period-col :deep(.dashboard-date-range__trigger),
.filter-search-col :deep(.form-control),
.filter-search-col :deep(.input-group-text),
.filter-select-col :deep(.select-trigger),
.filter-select-col :deep(.form-select) {
    height: 34px !important;
    min-height: 34px !important;
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
    font-size: 0.75rem !important;
}

.filter-period-col :deep(.dashboard-date-range__trigger) {
    gap: 0.35rem;
    padding-right: 0.5rem !important;
    padding-left: 0.5rem !important;
}

.filter-period-col :deep(.dashboard-date-range__value),
.filter-select-col :deep(.select-trigger-label) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.app-index-collapsible-filters__btn) {
    height: 34px !important;
    padding: 0.25rem 0.5rem !important;
    font-size: 0.75rem !important;
}

.filter-search-col :deep(.app-index-search > .form-control),
.filter-search-col :deep(.app-index-search > .input-group-text) {
    min-height: 34px !important;
}

/* ── Mobile Responsive Layout (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {
    :deep(.app-index-card-toolbar) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 6px !important;
        width: 100% !important;
        margin-top: 0.35rem !important;
    }

    .maintenance-header-btn,
    :deep(.maintenance-header-btn) {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        font-size: 0.72rem !important;
        padding: 0.25rem 0.35rem !important;
        height: 30px !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .maintenance-header-btn i,
    :deep(.maintenance-header-btn i) {
        font-size: 0.75rem !important;
    }

    .maintenance-header-btn--add,
    :deep(.maintenance-header-btn--add),
    .maintenance-header-btn--add span,
    :deep(.maintenance-header-btn--add span),
    :deep(.maintenance-header-btn--add span),
    .maintenance-header-btn--add i,
    :deep(.maintenance-header-btn--add i) {
        color: var(--brand-primary, #2fb36f) !important;
        font-weight: 600 !important;
    }

    .maintenance-header-btn--filter,
    :deep(.maintenance-header-btn--filter),
    .maintenance-header-btn--filter:hover,
    :deep(.maintenance-header-btn--filter:hover),
    .maintenance-header-btn--filter:focus,
    :deep(.maintenance-header-btn--filter:focus),
    .maintenance-header-btn--filter:active,
    :deep(.maintenance-header-btn--filter:active) {
        border-radius: 8px !important;
        border: 1px solid #ffffff !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        color: var(--brand-primary, #2fb36f) !important;
        height: 30px !important;
        padding: 0.25rem 0.35rem !important;
        flex: 1 1 0 !important;
        min-width: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .maintenance-header-btn--filter .filter-btn-text,
    :deep(.maintenance-header-btn--filter .filter-btn-text),
    .maintenance-header-btn--filter .filter-slider-icon,
    :deep(.maintenance-header-btn--filter .filter-slider-icon),
    .maintenance-header-btn--filter .filter-chevron-icon,
    :deep(.maintenance-header-btn--filter .filter-chevron-icon),
    .maintenance-header-btn--filter svg,
    :deep(.maintenance-header-btn--filter svg),
    .maintenance-header-btn--filter span,
    :deep(.maintenance-header-btn--filter span) {
        color: var(--brand-primary, #2fb36f) !important;
        fill: currentColor !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        padding: 0.5rem 0.65rem !important;
    }

    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    /* 2 input boxes per line on mobile */
    .filter-period-col,
    .filter-search-col,
    .filter-select-col {
        flex: 0 0 calc(50% - 4px) !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
    }

    :deep(.app-index-collapsible-filters .form-label),
    :deep(.dashboard-date-range__label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    :deep(.app-index-collapsible-filters .form-control),
    :deep(.app-index-collapsible-filters .select-trigger),
    :deep(.dashboard-date-range__trigger) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.dashboard-date-range__value),
    :deep(.dashboard-date-range__prefix),
    :deep(.app-index-collapsible-filters .select-trigger-label) {
        font-size: 0.75rem !important;
    }

    :deep(.select-trigger-icon),
    :deep(.dashboard-date-range__icon),
    :deep(.dashboard-date-range__chevron) {
        font-size: 0.65rem !important;
    }

    /* Search and Reset buttons in 1 single row on mobile */
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

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: 28px !important;
        min-height: 28px !important;
        font-size: 0.75rem !important;
        padding: 0.15rem 0.5rem !important;
        border-radius: 6px !important;
    }

    /* Table micro typography and padding on mobile */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    .table {
        font-size: 0.72rem !important;
    }

    .table th {
        font-size: 0.72rem !important;
        padding: 0.4rem 0.35rem !important;
        white-space: nowrap !important;
    }

    .table td {
        font-size: 0.72rem !important;
        padding: 0.35rem 0.35rem !important;
        vertical-align: middle !important;
    }

    .badge {
        font-size: 0.65rem !important;
        padding: 0.2rem 0.35rem !important;
    }

    :deep(.app-table-actions) {
        gap: 3px !important;
    }

    :deep(.app-crud-action-button),
    :deep(.app-index-action-btn) {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        min-height: 26px !important;
        font-size: 0.7rem !important;
        padding: 0 !important;
    }

    :deep(.app-crud-action-button i),
    :deep(.app-index-action-btn i),
    :deep(.app-index-action-btn .fas) {
        font-size: 0.6875rem !important;
    }

    /* Pagination centered in middle on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.45rem !important;
        padding: 0.6rem 0.6rem !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.75rem !important;
        text-align: center !important;
        width: 100% !important;
        margin: 0 auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: auto !important;
        margin-right: auto !important;
        display: flex !important;
        justify-content: center !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__list) {
        margin-left: auto !important;
        margin-right: auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.22rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

/* ── Extra-Small Mobile Devices (< 380px) ─────────────────────────── */
@media (max-width: 380px) {
    .maintenance-header-btn {
        font-size: 0.65rem !important;
        padding: 0.25rem 0.15rem !important;
        height: 28px !important;
    }

    .maintenance-header-btn svg,
    .maintenance-header-btn i,
    .maintenance-header-btn :deep(i) {
        font-size: 0.68rem !important;
        margin-right: 1px !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>
