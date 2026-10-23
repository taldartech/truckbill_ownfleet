<template>
    <div class="container mt-2 mt-md-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before adding trucks.
        </div>

        <AppIndexListCard
            title="Truck Management"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading trucks…"
        >
            <template #toolbar>
                <RouterLink
                    v-if="canInCompany('vehicles.view')"
                    :to="{ name: 'trucks.archived' }"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Archived Trucks"
                    aria-label="Archived Trucks"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 12.5V5a1 1 0 0 1-1-1zm2 3v7.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V5zm13-3H1v2h14zM5 7.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1">Archived</span>
                </RouterLink>
                <button
                    v-if="canCreate"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Bulk Upload Trucks"
                    aria-label="Bulk Upload Trucks"
                    @click="showBulkUpload = true"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/>
                        <path fill-rule="evenodd" d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708z"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Bulk </span>Upload</span>
                </button>
                <button
                    v-if="canCreate"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Add Truck"
                    aria-label="Add Truck"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Add </span>Truck</span>
                </button>
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

                <div v-show="filtersOpen" class="trucks-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="trucks-filters-panel"
                        :loading="loading"
                        @search="loadVehicles(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label" for="trucksIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="filters.search"
                                input-id="trucksIndex_search"
                                placeholder="Reg no, make, model"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter="loadVehicles(1)"
                            />
                        </div>
                        <div class="filter-select-col">
                            <label class="form-label">Truck Type</label>
                            <AppSelect
                                v-model="filters.vehicle_type"
                                :options="vehicleTypes"
                                placeholder="All types"
                                search-placeholder="Search type…"
                            />
                        </div>
                        <div class="filter-select-col">
                            <label class="form-label">Status</label>
                            <AppSelect
                                v-model="filters.status"
                                :options="truckStatusFilterOptions"
                                placeholder="All statuses"
                                search-placeholder="Search status…"
                            />
                        </div>
                        <div class="filter-select-col">
                            <label class="form-label">Driver Assignment</label>
                            <AppSelect
                                v-model="filters.primary_driver"
                                :options="primaryDriverFilterOptions"
                                placeholder="All trucks"
                                search-placeholder="Search…"
                            />
                        </div>
                    </AppIndexCollapsibleFilters>
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px" class="text-center">#</th>
                        <th class="text-nowrap" style="min-width: 8rem;">Registration</th>
                        <th class="text-nowrap" style="min-width: 6.5rem;">Type</th>
                        <th style="min-width: 7.5rem;">Make / Model</th>
                        <th style="min-width: 8.5rem;">Driver Assignment</th>
                        <th class="text-nowrap" style="min-width: 6.5rem;">Meter (KM)</th>
                        <th class="text-center text-nowrap" style="min-width: 5.5rem;">Status</th>
                        <th class="app-index-actions-cell text-center text-nowrap" style="width: 140px; min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="vehicles.length === 0" class="app-index-empty-row">
                        <td colspan="8" class="text-center text-muted py-4">
                            {{ filtersActive ? 'No trucks found for selected filters.' : 'No trucks found. Add your first truck.' }}
                        </td>
                    </tr>
                  
                    <tr v-for="(vehicle, index) in vehicles" :key="vehicle.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">
                            <RouterLink
                                :to="{ name: 'trucks.show', params: { vehicleId: vehicle.id } }"
                                class="truck-registration-link fw-semibold link-primary"
                                title="View truck details"
                            >
                                {{ vehicle.registration_number }}
                            </RouterLink>
                        </td>
                        <td class="text-nowrap">{{ vehicle.vehicle_type_label ?? vehicle.vehicle_type }}</td>
                        <td>{{ formatMakeModel(vehicle) }}</td>
                        <td class="primary-driver-cell">
                            <div
                                v-if="vehicle.current_primary_assignment?.driver?.name"
                                class="primary-driver-assigned"
                            >
                                <span class="primary-driver-name">
                                    {{ vehicle.current_primary_assignment.driver.name }}
                                </span>
                                <button
                                    v-if="canManageAssignments"
                                    type="button"
                                    class="primary-driver-change"
                                    title="Change primary driver"
                                    @click="openAssignment(vehicle)"
                                >
                                    Change
                                </button>
                            </div>
                            <div
                                v-else-if="canManageAssignments"
                                class="primary-driver-empty"
                            >
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm primary-driver-assign-btn"
                                    title="Assign primary driver"
                                    @click="openAssignment(vehicle)"
                                >
                                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                                    Assign
                                </button>
                            </div>
                        </td>
                        <td class="text-nowrap">{{ vehicle.odometer_km ?? '0' }}</td>
                        <td class="text-center text-nowrap">
                            <div v-if="canToggleStatus" class="status-toggle-wrapper">
                                <div class="form-check form-switch mb-0">
                                    <input
                                        :id="`truckStatus_${vehicle.id}`"
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        :checked="vehicle.is_active"
                                        :disabled="togglingId === vehicle.id"
                                        @change="handleToggleStatus(vehicle, $event)"
                                    />
                                </div>
                                <label
                                    :for="`truckStatus_${vehicle.id}`"
                                    class="status-toggle-label small"
                                >
                                    {{ vehicle.is_active ? 'Active' : 'Inactive' }}
                                </label>
                            </div>
                            <span
                                v-else
                                class="badge"
                                :class="vehicle.is_active ? 'bg-success' : 'bg-secondary'"
                            >
                                {{ vehicle.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="app-index-actions-cell text-center text-nowrap">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canViewDocuments"
                                    action="documents"
                                    @click="openDocuments(vehicle)"
                                />
                                <AppCrudActionButton
                                    v-if="canViewTyres"
                                    action="tyres"
                                    title="Tyre positions"
                                    :to="{ name: 'vehicles.tyres', params: { vehicleId: vehicle.id } }"
                                />
                                <AppCrudActionButton
                                    v-if="canCreateMaintenance"
                                    action="service"
                                    title="Add service record"
                                    :to="{ name: 'maintenance.create', query: { vehicle_id: vehicle.id } }"
                                />
                                <AppCrudActionButton
                                    v-if="canEdit"
                                    action="edit"
                                    @click="openEdit(vehicle)"
                                />
                                <AppCrudActionButton
                                    v-if="canDelete"
                                    action="delete"
                                    @click="handleDelete(vehicle)"
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
                    item-label="trucks"
                    aria-label="Trucks pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <VehicleFormModal
            modal-id="vehicleFormModal"
            :show="showForm"
            :mode="formMode"
            :vehicle="editingVehicle"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="vehicleFormError"
            :can-manage-types="canManage"
            @close="closeForm"
            @submit="handleSubmit"
        />

        <VehicleDocumentsModal
            modal-id="vehicleDocumentsModal"
            :show="showDocuments"
            :vehicle="documentsVehicle"
            :can-create="canCreateDocuments"
            :can-edit="canEditDocuments"
            :can-delete="canDeleteDocuments"
            @close="closeDocuments"
        />

        <VehicleDriverAssignmentModal
            modal-id="vehicleDriverAssignmentModal"
            :show="showAssignment"
            :vehicle="assignmentVehicle"
            :can-create="canCreateAssignments"
            :can-edit="canEditAssignments"
            @close="closeAssignment"
            @assigned="onAssignmentSaved"
        />



        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />

        <BulkUploadModal
            v-model:show="showBulkUpload"
            title="Bulk Upload Trucks"
            upload-url="/app/api/vehicles/bulk-upload"
            template-download-url="/app/api/vehicles/bulk-upload/template"
            template-file-name="vehicles_bulk_upload_template.xlsx"
            :columns="bulkUploadColumns"
            @success="loadVehicles(1)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    createVehicle,
    defaultVehicleListFilters,
    deleteVehicle,
    listAllVehicles,
    listVehicles,
    toggleVehicleStatus,
    updateVehicle,
} from '../../api/vehicles';
import { listAllTruckTypes } from '../../api/truckTypes';
import VehicleDocumentsModal from '../../components/trucks/VehicleDocumentsModal.vue';
import VehicleDriverAssignmentModal from '../../components/trucks/VehicleDriverAssignmentModal.vue';
import VehicleFormModal from '../../components/trucks/VehicleFormModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import BulkUploadModal from '../../components/ui/BulkUploadModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildTruckFields } from '../../utils/masterCrudSaveConfirm';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';

const route = useRoute();
const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const { promptDelete } = useDeleteConfirm();

const { canInCompany, canCreateInCompany, canEditInCompany, canDeleteRecords } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canCreate = computed(() => canCreateInCompany('vehicles'));
const canEdit = computed(() => canEditInCompany('vehicles'));
const canManage = computed(() => canCreate.value || canEdit.value);
const canDelete = computed(() => canInCompany('vehicles.delete'));
const canToggleStatus = computed(() => canInCompany('vehicles.toggle_status'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const canViewAssignments = computed(() => canInCompany('driver_assignments.view'));
const canCreateAssignments = computed(() => canInCompany('driver_assignments.create'));
const canEditAssignments = computed(() => canInCompany('driver_assignments.edit'));
const canManageAssignments = computed(
    () => canCreateAssignments.value || canEditAssignments.value,
);
const canViewDocuments = computed(() => canInCompany('vehicle_documents.view'));
const canCreateDocuments = computed(() => canCreateInCompany('vehicle_documents'));
const canEditDocuments = computed(() => canEditInCompany('vehicle_documents'));
const canDeleteDocuments = computed(
    () => canDeleteRecords.value && canInCompany('vehicle_documents.manage'),
);
const canCreateMaintenance = computed(() => canInCompany('maintenance.create'));

const vehicles = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingVehicle = ref(null);
const { fieldErrors, formError: vehicleFormError, clearErrors, applyApiError } = useApiFormErrors();
const showDocuments = ref(false);
const documentsVehicle = ref(null);
const showAssignment = ref(false);
const assignmentVehicle = ref(null);
const showBulkUpload = ref(false);

const bulkUploadColumns = [
    { name: 'registration_number', label: 'Vehicle Number', required: true, description: 'Vehicle registration number (e.g. RJ27GB1234)' },
    { name: 'vehicle_type', label: 'Vehicle Type', required: true, description: 'Active truck type name or code' },
    { name: 'make', label: 'Make', required: false, description: 'Manufacturer (e.g. Tata, BharatBenz)' },
    { name: 'model', label: 'Model', required: false, description: 'Vehicle model' },
    { name: 'manufacture_year', label: 'Manufacture Year', required: false, description: 'Year between 1980 and current year' },
    { name: 'odometer_km', label: 'Odometer KM', required: false, description: 'Current odometer reading' },
    { name: 'default_mileage_km_per_liter', label: 'Mileage KM/L', required: false, description: 'Standard fuel mileage' },
    { name: 'max_load_ton', label: 'Max Load Ton', required: false, description: 'Maximum payload capacity in tons' },
    { name: 'fuel_tank_capacity_ltr', label: 'Fuel Tank Capacity Ltr', required: false, description: 'Fuel tank capacity in liters' },
    { name: 'tyre_layout', label: 'Tyre Layout', required: false, description: 'Active tyre layout code (defaults to 10 Wheeler)' },
];
const togglingId = ref(null);
const truckTypes = ref([]);


const vehicleTypes = computed(() =>
    truckTypes.value.map((type) => ({
        value: type.code,
        label: type.name,
    })),
);
const truckStatusFilterOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];
const primaryDriverFilterOptions = [
    { value: 'assigned', label: 'Assigned' },
    { value: 'unassigned', label: 'Unassigned' },
];
const filters = reactive(defaultVehicleListFilters());
const defaultFilters = defaultVehicleListFilters();
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersActive = computed(() => {
    return (
        filters.search.trim() !== defaultFilters.search ||
        filters.vehicle_type !== defaultFilters.vehicle_type ||
        filters.status !== defaultFilters.status ||
        filters.primary_driver !== defaultFilters.primary_driver
    );
});


function rowNumber(index) {
    return (pagination.currentPage - 1) * pagination.perPage + index + 1;
}


function formatMakeModel(vehicle) {
    const parts = [vehicle.make, vehicle.model].filter(Boolean);

    return parts.length ? parts.join(' / ') : '-';
}

async function loadTruckTypes() {
    if (needsCompanySelection.value) {
        truckTypes.value = [];

        return;
    }

    try {
        truckTypes.value = await listAllTruckTypes();
    } catch {
        truckTypes.value = [];
    }
}

async function loadVehicles(page = pagination.currentPage) {
    if (needsCompanySelection.value) {
        vehicles.value = [];
        loading.value = false;
        resetPagination();

        return;
    }

    loading.value = true;

    try {
        const response = await listVehicles({
            page,
            perPage: pagination.perPage,
            search: filters.search.trim(),
            vehicleType: filters.vehicle_type,
            status: filters.status,
            primaryDriver: filters.primary_driver,
        });
        vehicles.value = response.data;
        syncFromMeta(response.meta, vehicles.value.length);
    } catch (error) {
        if (!handleCompanyContextError(error, 'Could not load trucks.')) {
            toast.error('Could not load trucks.');
        }

        vehicles.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadVehicles(page);
}

function clearFilters() {
    Object.assign(filters, defaultVehicleListFilters());
    loadVehicles(1);
}

function openCreate() {
    formMode.value = 'create';
    editingVehicle.value = null;
    clearErrors();
    showForm.value = true;
}

function openEdit(vehicle) {
    formMode.value = 'edit';
    editingVehicle.value = { ...vehicle };
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingVehicle.value = null;
    clearErrors();
}

function openDocuments(vehicle) {
    documentsVehicle.value = { ...vehicle };
    showDocuments.value = true;
}

function closeDocuments() {
    showDocuments.value = false;
    documentsVehicle.value = null;
}

function openAssignment(vehicle) {
    assignmentVehicle.value = { ...vehicle };
    showAssignment.value = true;
}

function closeAssignment() {
    showAssignment.value = false;
    assignmentVehicle.value = null;
}

async function onAssignmentSaved(action) {
    if (action === 'save' || action === 'remove') {
        closeAssignment();
    }
    
    if (action === 'save') {
        toast.success('Driver assignment saved.');
    } else if (action === 'remove') {
        toast.success('Driver removed successfully.');
    } else if (action === 'delete') {
        toast.success('Assignment history deleted successfully.');
    } else {
        toast.success('Driver assignment saved.');
    }
    await loadVehicles();
}

function handleSubmit(payload) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Truck',
        mode: formMode.value === 'edit' ? 'edit' : 'create',
        fields: buildTruckFields(payload, editingVehicle.value),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        if (formMode.value === 'edit' && editingVehicle.value?.id) {
            await updateVehicle(editingVehicle.value.id, payload);
            toast.success('Truck updated successfully.');
        } else {
            await createVehicle(payload);
            toast.success('Truck added successfully.');
        }

        finishSaveConfirm();
        closeForm();
        await loadVehicles();
    } catch (error) {
        finishSaveConfirm();

        if (!applyApiError(error, 'Please check the highlighted fields.')) {
            if (!handleCompanyContextError(error, 'Select a company before saving a truck.')) {
                toast.error('Unable to save truck.');
            }
        }
    } finally {
        saving.value = false;
    }
}

async function handleToggleStatus(vehicle, event) {
    const nextActive = event.target.checked;
    togglingId.value = vehicle.id;

    try {
        const updated = await toggleVehicleStatus(vehicle.id, nextActive);
        vehicle.is_active = updated.is_active;
        toast.success(updated.is_active ? 'Truck activated.' : 'Truck deactivated.');
    } catch {
        event.target.checked = vehicle.is_active;
        toast.error('Unable to update truck status.');
    } finally {
        togglingId.value = null;
    }
}

function handleDelete(vehicle) {
    promptDelete({
        title: 'Delete Truck?',
        itemName: vehicle.registration_number,
        details: [
            { label: 'Registration', value: vehicle.registration_number },
            { label: 'Truck Type', value: vehicle.vehicle_type_label ?? vehicle.vehicle_type },
            { label: 'Make', value: vehicle.make },
            { label: 'Model', value: vehicle.model },
            { label: 'Driver', value: vehicle.current_primary_assignment?.driver?.name ?? 'Not assigned' },
            { label: 'Meter Reading', value: vehicle.odometer_km ? `${vehicle.odometer_km} KM` : null },
        ],
        onConfirm: async (remark) => {
            await deleteVehicle(vehicle.id, { deleted_reason: remark, remark });
            toast.success('Truck deleted.');
            await loadVehicles();
        },
    });
}

function applyRouteFilters() {
    const { status, search } = route.query;

    if (typeof status === 'string' && status !== '') {
        filters.status = status;
    }

    if (typeof search === 'string' && search.trim() !== '') {
        filters.search = search.trim();
    }
}

async function resolveVehicleSearchFromRoute() {
    const search = route.query.search;

    if (typeof search === 'string' && search.trim() !== '') {
        filters.search = search.trim();

        return;
    }

    const vehicleId = route.query.vehicle_id;

    if (typeof vehicleId !== 'string' || vehicleId === '') {
        return;
    }

    try {
        const allVehicles = await listAllVehicles();
        const vehicle = allVehicles.find((item) => item.id === vehicleId);

        if (vehicle?.registration_number) {
            filters.search = vehicle.registration_number;
        }
    } catch {
        // Search filter stays empty if lookup fails.
    }
}

onMounted(async () => {
    applyRouteFilters();

    const ok = await guardCompanyContext();

    if (ok === true) {
        await resolveVehicleSearchFromRoute();
        await Promise.all([loadTruckTypes(), loadVehicles()]);
    }
});

watch(
    () => [route.query.search, route.query.vehicle_id],
    async () => {
        applyRouteFilters();
        await resolveVehicleSearchFromRoute();
        await loadVehicles(1);
    },
);
</script>

<style scoped>
.truck-registration-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.truck-registration-link:hover {
    text-decoration: underline;
}

.primary-driver-cell {
    min-width: 10rem;
}

.primary-driver-assigned {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.15rem;
}

.primary-driver-name {
    font-weight: 500;
    color: var(--brand-text-primary, #475569);
}

.primary-driver-change {
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 0.75rem;
    color: var(--brand-text-muted, #64748b);
    text-decoration: underline;
    cursor: pointer;
}

.primary-driver-change:hover {
    color: var(--brand-accent-blue, #2d8cff);
}

.primary-driver-empty {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.primary-driver-badge {
    background: #f1f5f9 !important;
    color: #64748b !important;
    border: 1px solid #e2e8f0;
    font-weight: 500;
}

.primary-driver-assign-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.55rem;
    font-size: 0.75rem;
    line-height: 1.4;
    border-color: #cbd5e1 !important;
    color: #475569 !important;
    background: #fff !important;
}

.primary-driver-assign-btn:hover {
    border-color: #94a3b8 !important;
    color: #334155 !important;
    background: #f8fafc !important;
}

.status-toggle-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 44px;
}

.status-toggle-wrapper .form-check {
    padding-left: 0;
    margin-bottom: 0;
    display: flex;
    justify-content: center;
}

.status-toggle-wrapper .form-check-input {
    float: none;
    margin-left: 0;
    cursor: pointer;
}

.status-toggle-label {
    cursor: pointer;
    margin-top: 2px;
    color: var(--brand-text-muted, #64748b);
    line-height: 1.2;
}

/* Custom filters layout */
@media (max-width: 991.98px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .filter-search-col {
        flex: 0 0 260px;
        width: 260px;
    }

    .filter-select-col {
        flex: 0 0 160px;
        width: 160px;
    }
}

:deep(.app-index-filters-fields) {
    display: contents;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

@media (min-width: 768px) {
    .trucks-filters-content {
        display: block !important;
    }
}

/* ── Mobile Responsive Styles (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {
    /* Filters: 2 per line on mobile */
    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        width: 100% !important;
    }

    :deep(.app-index-filters-fields > div),
    :deep(.filter-search-col),
    :deep(.filter-select-col),
    .filter-search-col,
    .filter-select-col {
        flex: 0 0 calc(50% - 4px) !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
    }

    :deep(.app-index-collapsible-filters .form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    :deep(.app-index-collapsible-filters .form-control),
    :deep(.app-index-collapsible-filters .select-trigger) {
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
    :deep(.app-index-collapsible-filters .select-trigger-label) {
        font-size: 0.75rem !important;
    }

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

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>
