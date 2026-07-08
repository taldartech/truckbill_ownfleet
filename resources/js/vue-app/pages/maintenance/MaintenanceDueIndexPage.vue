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
                <div class="maintenance-filter-search">
                    <label class="form-label" :for="`${pageId}_search`">Search</label>
                    <AppIndexSearchInput
                        v-model="filters.search"
                        :input-id="`${pageId}_search`"
                        placeholder="Truck, service type, provider…"
                        max-width="100%"
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
                <template v-if="filtersActive" #active-filters>
                    <div class="app-index-active-search">
                        <span class="app-index-active-search__label">Filtering:</span>
                        <span v-if="filters.search.trim()" class="app-index-active-search__pill">
                            {{ filters.search.trim() }}
                            <button
                                type="button"
                                aria-label="Clear search filter"
                                title="Clear search"
                                @click="clearSearchFilter"
                            >
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </span>
                    </div>
                </template>
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
                                    action="edit"
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
import { computed, onMounted, reactive, ref } from 'vue';
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
import { formatDate } from '../../utils/datetime';
import {
    isMaintenanceDateOverdue,
    isMaintenanceKmOverdue,
} from '../../utils/maintenanceDueStatus';

const pageId = 'maintenanceDueIndex';
const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();

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

const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersActive = computed(
    () =>
        filters.search.trim() !== '' ||
        filters.vehicle_id !== '' ||
        filters.maintenance_service_type_id !== '' ||
        filters.overdue_reason !== '',
);

const records = computed(() => {
    const start = (pagination.currentPage - 1) * pagination.perPage;

    return allRecords.value.slice(start, start + pagination.perPage);
});

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
    pagination.currentPage = 1;
    loadRecords(1);
}

function clearSearchFilter() {
    filters.search = '';
    applyFilters();
}

function clearFilters() {
    Object.assign(filters, defaultMaintenanceDueFilters());
    pagination.currentPage = 1;
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
        });
        syncClientPagination(allRecords.value.length, page);
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
}

function goToCreate() {
    router.push({ name: 'maintenance.create' });
}

function goToCreateForTruck(record) {
    if (!record.vehicle_id) {
        return;
    }

    router.push({ name: 'maintenance.create', query: { vehicle_id: record.vehicle_id } });
}

function goToView(record) {
    router.push({ name: 'maintenance.show', params: { id: record.id } });
}

onMounted(async () => {
    if (route.query.vehicle_id) {
        filters.vehicle_id = String(route.query.vehicle_id);
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
        await loadRecords(1);
    } else {
        loading.value = false;
    }
});
</script>
