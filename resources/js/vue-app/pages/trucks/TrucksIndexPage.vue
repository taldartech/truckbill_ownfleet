<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Truck Management">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before adding trucks.
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadVehicles(1)" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="filters.search"
                    input-id="trucksIndex_search"
                    placeholder="Registration, make, model…"
                    max-width="100%"
                    :disabled="loading"
                />
            </div>
            <div>
                <label class="form-label">Truck type</label>
                <select v-model="filters.vehicle_type" class="form-select">
                    <option value="">All types</option>
                    <option v-for="type in vehicleTypes" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="filters.status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label class="form-label">Primary driver</label>
                <select v-model="filters.primary_driver" class="form-select">
                    <option value="">All trucks</option>
                    <option value="assigned">Assigned</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading trucks…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Registration</th>
                            <th>Type</th>
                            <th>Tyre layout</th>
                            <th>Make / Model</th>
                            <th>Primary Driver</th>
                            <th>Odometer (KM)</th>
                            <th>Status</th>
                            <th class="app-index-actions-cell" style="width: 160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vehicles.length === 0">
                            <td colspan="8" class="text-center text-muted">
                                {{ filtersActive ? 'No trucks found for selected filters.' : 'No trucks found. Add your first truck.' }}
                            </td>
                        </tr>
                        <tr v-for="vehicle in vehicles" :key="vehicle.id">
                            <td>
                                <RouterLink
                                    :to="{
                                        name: 'trucks.index',
                                        query: { search: vehicle.registration_number },
                                    }"
                                    class="truck-registration-link"
                                    title="Find this truck"
                                >
                                    {{ vehicle.registration_number }}
                                </RouterLink>
                            </td>
                            <td>{{ vehicle.vehicle_type }}</td>
                            <td class="small">{{ vehicle.tyre_layout_label ?? vehicle.tyre_layout ?? '—' }}</td>
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
                                    <span class="badge rounded-pill primary-driver-badge">Unassigned</span>
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
                                <span v-else class="text-muted">Unassigned</span>
                            </td>
                            <td>{{ vehicle.odometer_km ?? '0' }}</td>
                            <td>
                                <div v-if="canToggleStatus" class="form-check form-switch mb-0">
                                    <input
                                        :id="`truckStatus_${vehicle.id}`"
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        :checked="vehicle.is_active"
                                        :disabled="togglingId === vehicle.id"
                                        @change="handleToggleStatus(vehicle, $event)"
                                    />
                                    <label
                                        :for="`truckStatus_${vehicle.id}`"
                                        class="form-check-label small"
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
                                    <AppCrudActionButton
                                        v-if="canViewAssignments"
                                        action="driver"
                                        @click="openAssignment(vehicle)"
                                    />
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
                                    <AppCrudActionButton
                                        v-if="canManage"
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
                </div>
            </div>
            <AppIndexPaginationFooter
                page-id="trucksIndex"
                :loading="loading"
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :per-page="pagination.perPage"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
                item-label="trucks"
                @page-change="goToPage"
                @update:per-page="onPerPageUpdate"
            />
        </div>

        <VehicleFormModal
            modal-id="vehicleFormModal"
            :show="showForm"
            :mode="formMode"
            :vehicle="editingVehicle"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="vehicleFormError"
            @close="closeForm"
            @submit="handleSubmit"
        />

        <VehicleDocumentsModal
            modal-id="vehicleDocumentsModal"
            :show="showDocuments"
            :vehicle="documentsVehicle"
            :can-manage="canManageDocuments"
            @close="closeDocuments"
        />

        <VehicleDriverAssignmentModal
            modal-id="vehicleDriverAssignmentModal"
            :show="showAssignment"
            :vehicle="assignmentVehicle"
            :can-manage="canManageAssignments"
            @close="closeAssignment"
            @assigned="onAssignmentSaved"
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
    VEHICLE_TYPES,
} from '../../api/vehicles';
import VehicleDocumentsModal from '../../components/trucks/VehicleDocumentsModal.vue';
import VehicleDriverAssignmentModal from '../../components/trucks/VehicleDriverAssignmentModal.vue';
import VehicleFormModal from '../../components/trucks/VehicleFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { crudActionButtonClasses } from '../../utils/crudActionPresets';

const tyreLinkClasses = crudActionButtonClasses('tyres');
const route = useRoute();

const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canManage = computed(() => canInCompany('vehicles.manage'));
const canDelete = computed(() => canInCompany('vehicles.delete'));
const canToggleStatus = computed(() => canInCompany('vehicles.toggle_status'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const canViewAssignments = computed(() => canInCompany('driver_assignments.view'));
const canManageAssignments = computed(() => canInCompany('driver_assignments.manage'));
const canViewDocuments = computed(() => canInCompany('vehicle_documents.view'));
const canManageDocuments = computed(() => canInCompany('vehicle_documents.manage'));

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
const flash = reactive({ success: '', error: '' });
const togglingId = ref(null);
const vehicleTypes = VEHICLE_TYPES;
const filters = reactive(defaultVehicleListFilters());
const defaultFilters = defaultVehicleListFilters();
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(25);

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

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

async function loadVehicles(page = pagination.currentPage) {
    if (needsCompanySelection.value) {
        vehicles.value = [];
        loading.value = false;
        resetPagination();

        return;
    }

    loading.value = true;
    flash.error = '';

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
            showToast('Could not load trucks.', 'error');
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

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    pagination.currentPage = 1;
    loadVehicles(1);
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
    flash.success = '';
    flash.error = '';
    assignmentVehicle.value = { ...vehicle };
    showAssignment.value = true;
}

function closeAssignment() {
    showAssignment.value = false;
    assignmentVehicle.value = null;
}

async function onAssignmentSaved() {
    showToast('Driver assignment saved.');
    await loadVehicles();
}

async function handleSubmit(payload) {
    saving.value = true;
    clearErrors();

    try {
        if (formMode.value === 'edit' && editingVehicle.value?.id) {
            await updateVehicle(editingVehicle.value.id, payload);
            showToast('Truck updated successfully.');
        } else {
            await createVehicle(payload);
            showToast('Truck added successfully.');
        }

        closeForm();
        await loadVehicles();
    } catch (error) {
        if (!applyApiError(error, 'Please check the highlighted fields.')) {
            if (!handleCompanyContextError(error, 'Select a company before saving a truck.')) {
                showToast('Unable to save truck.', 'error');
            }
        }
    } finally {
        saving.value = false;
    }
}

async function handleToggleStatus(vehicle, event) {
    const nextActive = event.target.checked;
    togglingId.value = vehicle.id;
    flash.error = '';

    try {
        const updated = await toggleVehicleStatus(vehicle.id, nextActive);
        vehicle.is_active = updated.is_active;
        showToast(updated.is_active ? 'Truck activated.' : 'Truck deactivated.');
    } catch {
        event.target.checked = vehicle.is_active;
        showToast('Unable to update truck status.', 'error');
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
        showToast('Truck deleted.');
        await loadVehicles();
    } catch {
        showToast('Unable to delete truck.', 'error');
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
        await loadVehicles();
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
</style>
