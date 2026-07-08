<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before managing maintenance records.
        </div>

        <AppIndexListCard title="Service Records" :loading="loading" loading-text="Loading service records…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="maintenance-records-filters-panel"
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
                panel-id="maintenance-records-filters-panel"
                :loading="loading"
                @search="applyFilters"
                @reset="clearFilters"
            >
                <div class="maintenance-filter-period">
                    <DashboardDateRangePicker
                        v-model:from-date="filters.from_date"
                        v-model:to-date="filters.to_date"
                        label="Date Range *"
                        panel-align="start"
                        :preset-keys="datePresetKeys"
                        :max-months="MAX_LIST_DATE_RANGE_MONTHS"
                        @change="onDateRangeChange"
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
                    <label class="form-label">Status</label>
                    <AppSelect
                        v-model="filters.due_status"
                        :options="dueStatusOptions"
                        placeholder="All statuses"
                        search-placeholder="Search status…"
                        :disabled="loading"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Date</th>
                        <th>Truck</th>
                        <th>Service Type</th>
                        <th>Provider</th>
                        <th class="text-end">Meter Reading</th>
                        <th class="text-end">Cost</th>
                        <th>Status</th>
                        <th>Next Due</th>
                        <th v-if="hasRowActions" class="app-index-actions-cell" style="width: 160px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!filtersApplied" class="app-index-empty-row">
                        <td :colspan="hasRowActions ? 10 : 9" class="text-center text-muted py-4">
                            Select a date range and click Search to view service records.
                        </td>
                    </tr>
                    <tr v-else-if="records.length === 0" class="app-index-empty-row">
                        <td :colspan="hasRowActions ? 10 : 9" class="text-center text-muted py-4">
                            No service records found for selected filters.
                        </td>
                    </tr>
                    <tr v-for="(record, index) in records" :key="record.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>{{ formatDate(record.service_date) }}</td>
                        <td>
                            <RouterLink
                                v-if="canView && record.vehicle?.id"
                                :to="{ name: 'trucks.show', params: { vehicleId: record.vehicle.id } }"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ vehicleLabel(record) }}
                            </RouterLink>
                            <span v-else>{{ vehicleLabel(record) }}</span>
                        </td>
                        <td>{{ record.service_type_label ?? record.service_type ?? '—' }}</td>
                        <td>{{ record.service_provider || '—' }}</td>
                        <td class="text-end">{{ formatOdometer(record.odometer_km) }}</td>
                        <td class="text-end">{{ formatAmount(record.cost_amount) }}</td>
                        <td>
                            <span class="badge" :class="statusMeta(record).badge">{{ statusMeta(record).label }}</span>
                        </td>
                        <td>{{ dueSummary(record) }}</td>
                        <td v-if="hasRowActions" class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canView"
                                    action="view"
                                    title="View record"
                                    @click="goToView(record)"
                                />
                                <AppCrudActionButton v-if="canEdit" action="edit" @click="goToEdit(record)" />
                                <AppCrudActionButton v-if="canDelete" action="delete" @click="openDelete(record)" />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="records"
                    aria-label="Service records pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <MaintenanceActionConfirmModal
            :show="showDeleteConfirm"
            :summary="deleteConfirmSummary"
            :deleting="deleting"
            @cancel="closeDeleteConfirm"
            @confirm="confirmDelete"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import {
    defaultMaintenanceListFilters,
    deleteVehicleMaintenance,
    listVehicleMaintenances,
    MAINTENANCE_DUE_STATUSES,
} from '../../api/vehicleMaintenances';
import MaintenanceActionConfirmModal from '../../components/maintenance/MaintenanceActionConfirmModal.vue';
import DashboardDateRangePicker from '../../components/dashboard/DashboardDateRangePicker.vue';
import MaintenanceServiceTypeSelect from '../../components/ui/MaintenanceServiceTypeSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
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
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { maintenanceDueStatusMeta, resolveMaintenanceDueStatus } from '../../utils/maintenanceDueStatus';
import { buildMaintenanceDeleteConfirmViewModel } from '../../utils/maintenanceSaveConfirm';

const pageId = 'maintenanceRecordsIndex';
const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();

const canView = computed(() => canInCompany('maintenance.view'));
const canCreate = computed(() => canInCompany('maintenance.create'));
const canEdit = computed(() => canInCompany('maintenance.edit'));
const canDelete = computed(() => canInCompany('maintenance.delete'));
const hasRowActions = computed(() => canView.value || canEdit.value || canDelete.value);

const serviceTypes = ref([]);
const vehicles = ref([]);
const records = ref([]);
const loading = ref(false);
const filtersApplied = ref(false);
const filtersOpen = ref(false);
const deleting = ref(false);
const showDeleteConfirm = ref(false);
const deleteConfirmSummary = ref(null);
const deletingRecord = ref(null);

const filters = reactive(defaultMaintenanceListFilters());
const defaultFilters = defaultMaintenanceListFilters();
const dueStatusOptions = MAINTENANCE_DUE_STATUSES;
const datePresetKeys = TRIP_LIST_DATE_PRESET_KEYS;

const { pagination, syncFromMeta } = useListPagination(10);

const filtersActive = computed(
    () =>
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.vehicle_id !== '' ||
        filters.maintenance_service_type_id !== '' ||
        filters.due_status !== '',
);

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

function statusMeta(record) {
    const status = record.due_status ?? resolveMaintenanceDueStatus(record);

    return maintenanceDueStatusMeta(status);
}

function dueSummary(record) {
    const parts = [];

    if (record.next_service_due_km) {
        parts.push(`${formatOdometer(record.next_service_due_km)} km`);
    }

    if (record.next_service_due_date) {
        parts.push(formatDate(record.next_service_due_date));
    }

    return parts.length ? parts.join(' / ') : '—';
}

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN');
}

function formatAmount(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
}

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

function showRouteFlash() {
    if (route.query.created === '1') {
        toast.success('Maintenance record created.');
    } else if (route.query.updated === '1') {
        toast.success('Maintenance record updated.');
    } else if (route.query.deleted === '1') {
        toast.success('Maintenance record deleted.');
    }
}

function applyFilters() {
    if (!filters.from_date || !filters.to_date) {
        toast.error('Select a date range before applying filters.');

        return;
    }

    if (dateRangeExceedsMonths(filters.from_date, filters.to_date, MAX_LIST_DATE_RANGE_MONTHS)) {
        toast.error(dateRangeMonthsError(MAX_LIST_DATE_RANGE_MONTHS));

        return;
    }

    filtersApplied.value = true;
    pagination.currentPage = 1;
    loadRecords(1);
}

function onDateRangeChange() {
    filtersApplied.value = false;
    records.value = [];
    syncFromMeta({}, 0);
}

function clearFilters() {
    Object.assign(filters, defaultMaintenanceListFilters());
    pagination.currentPage = 1;
    filtersApplied.value = false;
    records.value = [];
    syncFromMeta({}, 0);
}

async function loadRecords(page = pagination.currentPage) {
    if (!canView.value || !filtersApplied.value) {
        records.value = [];
        loading.value = false;

        return;
    }

    if (!filters.from_date || !filters.to_date) {
        filtersApplied.value = false;
        records.value = [];
        syncFromMeta({}, 0);

        return;
    }

    loading.value = true;

    try {
        const response = await listVehicleMaintenances({
            page,
            perPage: pagination.perPage,
            vehicleId: filters.vehicle_id,
            maintenanceServiceTypeId: filters.maintenance_service_type_id,
            from: filters.from_date,
            to: filters.to_date,
            dueStatus: filters.due_status,
        });

        records.value = response.data;
        syncFromMeta(response.meta, records.value.length);
    } catch {
        toast.error('Could not load service records.');
        records.value = [];
        syncFromMeta({}, 0);
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (!filtersApplied.value || page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadRecords(page);
}

function goToCreate() {
    router.push({ name: 'maintenance.create' });
}

function goToView(record) {
    router.push({ name: 'maintenance.show', params: { id: record.id } });
}

function goToEdit(record) {
    router.push({ name: 'maintenance.edit', params: { id: record.id } });
}

function openDelete(record) {
    deletingRecord.value = record;
    deleteConfirmSummary.value = buildMaintenanceDeleteConfirmViewModel(record);
    showDeleteConfirm.value = true;
}

function closeDeleteConfirm() {
    showDeleteConfirm.value = false;
    deleteConfirmSummary.value = null;
    deletingRecord.value = null;
}

async function confirmDelete() {
    if (!deletingRecord.value?.id) {
        return;
    }

    deleting.value = true;

    try {
        await deleteVehicleMaintenance(deletingRecord.value.id);
        toast.success('Service record deleted.');
        closeDeleteConfirm();

        const page =
            records.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        if (filtersApplied.value) {
            await loadRecords(page);
        }
    } catch (error) {
        toast.error(friendlyApiErrorMessage(error, 'Unable to delete service record.'));
        closeDeleteConfirm();
    } finally {
        deleting.value = false;
    }
}

onMounted(async () => {
    showRouteFlash();

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
});
</script>
