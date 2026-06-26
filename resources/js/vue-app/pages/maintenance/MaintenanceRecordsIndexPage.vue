<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Service Records">
            <template v-if="canCreate" #actions>
                <AppButton icon="bi bi-plus" label="Add Service" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <AppIndexFiltersCard :active="filtersActive" @apply="applyFilters" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="searchQuery"
                    :input-id="`${pageId}_search`"
                    placeholder="Truck, provider, remarks…"
                    max-width="100%"
                    :disabled="loading"
                    @keydown.enter.prevent="applyFilters"
                />
            </div>
            <div>
                <label class="form-label" :for="`${pageId}_vehicle`">Truck</label>
                <SearchableSelect
                    :input-id="`${pageId}_vehicle`"
                    v-model="vehicleFilter"
                    :items="vehicleSelectOptions"
                    placeholder="All trucks"
                    search-placeholder="Search truck number…"
                    empty-message="No truck found."
                    item-icon-class="bi bi-truck"
                    attach-dropdown-to-body
                    :disabled="loading"
                />
            </div>
            <div>
                <label class="form-label" :for="`${pageId}_service_type`">Service Type</label>
                <SearchableSelect
                    :input-id="`${pageId}_service_type`"
                    v-model="serviceTypeFilter"
                    :items="serviceTypeSelectItems"
                    placeholder="All types"
                    search-placeholder="Search service type…"
                    empty-message="No service type found."
                    item-icon-class="bi bi-wrench-adjustable"
                    attach-dropdown-to-body
                    :disabled="loading"
                />
            </div>
            <div>
                <label class="form-label">From</label>
                <input v-model="fromFilter" type="date" class="form-control" :disabled="loading" />
            </div>
            <div>
                <label class="form-label">To</label>
                <input v-model="toFilter" type="date" class="form-control" :disabled="loading" />
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading service records…">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Date</th>
                        <th>Truck</th>
                        <th>Service Type</th>
                        <th>Provider</th>
                        <th class="text-end">Meter Reading</th>
                        <th class="text-end">Cost</th>
                        <th>Next Due</th>
                        <th v-if="hasRowActions" class="app-index-actions-cell" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="records.length === 0" class="app-index-empty-row">
                        <td :colspan="hasRowActions ? 9 : 8" class="text-center text-muted py-4">
                            No service records found.
                        </td>
                    </tr>
                    <tr v-for="(record, index) in records" :key="record.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>{{ formatDate(record.service_date) }}</td>
                        <td>{{ vehicleLabel(record) }}</td>
                        <td>{{ record.service_type_label ?? record.service_type ?? '—' }}</td>
                        <td>{{ record.service_provider || '—' }}</td>
                        <td class="text-end">{{ formatOdometer(record.odometer_km) }}</td>
                        <td class="text-end">{{ formatAmount(record.cost_amount) }}</td>
                        <td>{{ dueSummary(record) }}</td>
                        <td v-if="hasRowActions" class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton v-if="canEdit" action="edit" @click="openEdit(record)" />
                                <AppCrudActionButton v-if="canDelete" action="delete" @click="handleDelete(record)" />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer>
                <AppIndexPaginationFooter
                    :page-id="pageId"
                    :loading="loading"
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :per-page="pagination.perPage"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                    item-label="records"
                    @page-change="goToPage"
                    @update:per-page="onPerPageUpdate"
                />
            </template>
        </AppIndexListCard>

        <MaintenanceFormModal
            modal-id="createMaintenanceModal"
            :show="showCreate"
            mode="create"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeCreate"
            @submit="handleCreate"
        />

        <MaintenanceFormModal
            modal-id="editMaintenanceModal"
            :show="showEdit"
            mode="edit"
            :record="editingRecord"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeEdit"
            @submit="handleUpdate"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { buildVehicleSelectOptions, listAllVehicles } from '../../api/vehicles';
import { buildMaintenanceServiceTypeSelectOptions, listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import {
    createVehicleMaintenance,
    deleteVehicleMaintenance,
    listVehicleMaintenances,
    updateVehicleMaintenance,
} from '../../api/vehicleMaintenances';
import MaintenanceFormModal from '../../components/maintenance/MaintenanceFormModal.vue';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { formatDate } from '../../utils/datetime';
import { friendlyApiErrorMessage } from '../../utils/formErrors';

const pageId = 'maintenanceRecordsIndex';
const route = useRoute();
const toast = useAppToast();
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const { canInCompany } = usePermissions();

const canView = computed(() => canInCompany('maintenance.view'));
const canCreate = computed(() => canInCompany('maintenance.create'));
const canEdit = computed(() => canInCompany('maintenance.edit'));
const canDelete = computed(() => canInCompany('maintenance.delete'));
const hasRowActions = computed(() => canEdit.value || canDelete.value);

const serviceTypeOptions = computed(() => buildMaintenanceServiceTypeSelectOptions(serviceTypes.value));
const serviceTypeSelectItems = computed(() => [
    { id: '', label: 'All types', hint: '', icon: 'bi bi-wrench-adjustable' },
    ...serviceTypeOptions.value.map((type) => ({
        id: type.value,
        label: type.label,
        hint: type.subtitle ?? '',
        icon: 'bi bi-wrench-adjustable',
    })),
]);
const serviceTypes = ref([]);
const vehicles = ref([]);
const vehicleSelectOptions = computed(() => buildVehicleSelectOptions(vehicles.value));
const records = ref([]);
const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const showEdit = ref(false);
const editingRecord = ref(null);
const searchQuery = ref('');
const vehicleFilter = ref('');
const serviceTypeFilter = ref('');
const fromFilter = ref('');
const toFilter = ref('');

const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    perPage: 25,
    total: 0,
    from: 0,
    to: 0,
});

const filtersActive = computed(
    () =>
        searchQuery.value.trim() !== '' ||
        vehicleFilter.value !== '' ||
        serviceTypeFilter.value !== '' ||
        fromFilter.value !== '' ||
        toFilter.value !== '',
);

function vehicleLabel(record) {
    const label = record.vehicle?.registration_number ?? '—';

    if (record.vehicle?.is_active === false) {
        return `${label} (Inactive)`;
    }

    return label;
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

function applyFilters() {
    pagination.currentPage = 1;
    loadRecords(1);
}

function clearFilters() {
    searchQuery.value = '';
    vehicleFilter.value = '';
    serviceTypeFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
    applyFilters();
}

async function loadRecords(page = pagination.currentPage) {
    if (!canView.value) {
        records.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        const response = await listVehicleMaintenances({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
            vehicleId: vehicleFilter.value,
            maintenanceServiceTypeId: serviceTypeFilter.value,
            from: fromFilter.value,
            to: toFilter.value,
        });

        records.value = response.data;
        pagination.currentPage = response.meta.current_page ?? page;
        pagination.lastPage = response.meta.last_page ?? 1;
        pagination.total = response.meta.total ?? records.value.length;
        pagination.from = response.meta.from ?? (records.value.length > 0 ? 1 : 0);
        pagination.to = response.meta.to ?? records.value.length;
    } catch {
        toast.error('Could not load service records.');
        records.value = [];
        pagination.currentPage = 1;
        pagination.lastPage = 1;
        pagination.total = 0;
        pagination.from = 0;
        pagination.to = 0;
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadRecords(page);
}

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    pagination.currentPage = 1;
    loadRecords(1);
}

function openCreate() {
    clearErrors();
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    clearErrors();
}

function openEdit(record) {
    editingRecord.value = { ...record };
    clearErrors();
    showEdit.value = true;
}

function closeEdit() {
    showEdit.value = false;
    editingRecord.value = null;
    clearErrors();
}

function applyValidationError(error, fallback = 'Unable to save service record.') {
    if (applyApiError(error, fallback)) {
        toast.error(formError.value || fallback);

        return;
    }

    toast.error(friendlyApiErrorMessage(error, fallback));
}

async function handleCreate(payload) {
    saving.value = true;
    clearErrors();

    try {
        await createVehicleMaintenance(payload);
        toast.success('Service record added.');
        closeCreate();
        await loadRecords(1);
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleUpdate(payload) {
    if (!editingRecord.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await updateVehicleMaintenance(editingRecord.value.id, payload);
        toast.success('Service record updated.');
        closeEdit();
        await loadRecords();
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleDelete(record) {
    const truck = vehicleLabel(record);

    if (!window.confirm(`Delete service record for ${truck} on ${formatDate(record.service_date)}?`)) {
        return;
    }

    try {
        await deleteVehicleMaintenance(record.id);
        toast.success('Service record deleted.');

        const page =
            records.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        await loadRecords(page);
    } catch (error) {
        toast.error(friendlyApiErrorMessage(error, 'Unable to delete service record.'));
    }
}

onMounted(async () => {
    if (route.query.vehicle_id) {
        vehicleFilter.value = String(route.query.vehicle_id);
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
        loadRecords();
    } else {
        loading.value = false;
    }
});
</script>
