<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before adding trucks.
        </div>

        <AppIndexListCard title="Truck Management" :loading="loading" loading-text="Loading trucks…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Create truck"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

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
                    <label class="form-label">Truck type</label>
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

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Registration</th>
                        <th>Type</th>
                        <!-- <th>Tyre layout</th> -->
                        <th>Make / Model</th>
                        <th>Driver Assignment</th>
                        <th>Meter Reading (KM)</th>
                        <th>Status</th>
                        <th class="app-index-actions-cell" style="width: 160px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="vehicles.length === 0" class="app-index-empty-row">
                        <td colspan="8" class="text-center text-muted py-4">
                            {{ filtersActive ? 'No trucks found for selected filters.' : 'No trucks found. Add your first truck.' }}
                        </td>
                    </tr>
                    <tr v-for="vehicle in vehicles" :key="vehicle.id">
                            <td>
                                <RouterLink
                                    :to="{ name: 'trucks.show', params: { vehicleId: vehicle.id } }"
                                    class="truck-registration-link"
                                    title="View truck details"
                                >
                                    {{ vehicle.registration_number }}
                                </RouterLink>
                            </td>
                            <td>{{ vehicle.vehicle_type_label ?? vehicle.vehicle_type }}</td>
                            <!-- <td class="small">{{ vehicle.tyre_layout_label ?? vehicle.tyre_layout ?? '—' }}</td> -->
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
                                <!-- <span v-else class="text-muted">Unassigned</span> -->
                            </td>
                            <td>{{ vehicle.odometer_km ?? '0' }}</td>
                            <td>
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
                            <td class="app-index-actions-cell">
                                <AppTableActions>
                                    <!--<AppCrudActionButton
                                        v-if="canViewAssignments"
                                        action="driver"
                                        @click="openAssignment(vehicle)"
                                    />-->
                                    <AppCrudActionButton
                                        v-if="canViewDocuments"
                                        action="documents"
                                        @click="openDocuments(vehicle)"
                                    />
                                    <RouterLink
                                        v-if="canViewTyres"
                                        :to="{ name: 'vehicles.tyres', params: { vehicleId: vehicle.id } }"
                                        :class="`${tyreLinkClasses} btn-icon`"
                                        title="Tyre positions"
                                    >
                                        <i class="bi bi-circle" aria-hidden="true"></i>
                                    </RouterLink>
                                    <RouterLink
                                        v-if="canCreateMaintenance"
                                        :to="{ name: 'maintenance.create', query: { vehicle_id: vehicle.id } }"
                                        :class="`${serviceLinkClasses} btn-icon`"
                                        title="Add service record"
                                    >
                                        <i class="fas fa-wrench" aria-hidden="true"></i>
                                    </RouterLink>
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
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildTruckFields } from '../../utils/masterCrudSaveConfirm';
import { crudActionButtonClasses } from '../../utils/crudActionPresets';

const tyreLinkClasses = crudActionButtonClasses('tyres');
const serviceLinkClasses = crudActionButtonClasses('service');
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
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersActive = computed(() => {
    return (
        filters.search.trim() !== defaultFilters.search ||
        filters.vehicle_type !== defaultFilters.vehicle_type ||
        filters.status !== defaultFilters.status ||
        filters.primary_driver !== defaultFilters.primary_driver
    );
});


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

async function onAssignmentSaved() {
    toast.success('Driver assignment saved.');
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

async function handleDelete(vehicle) {
    if (!window.confirm(`Delete truck ${vehicle.registration_number}?`)) {
        return;
    }

    try {
        await deleteVehicle(vehicle.id);
        toast.success('Truck deleted.');
        await loadVehicles();
    } catch {
        toast.error('Unable to delete truck.');
    }
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

/* Custom filters single row layout */
:deep(.app-index-collapsible-filters__inner) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

:deep(.app-index-filters-fields) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    flex: 1 1 auto;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

.filter-search-col {
    flex: 0 0 280px;
    width: 280px;
}

.filter-select-col {
    flex: 0 0 180px;
    width: 180px;
}
</style>
