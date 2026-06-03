<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Truck Management</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" label="Add Truck" @click="openCreate" />
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before adding trucks.
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Trucks List</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading trucks…</div>
                <table v-else class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Registration</th>
                            <th>Type</th>
                            <th>Make / Model</th>
                            <th>Primary Driver</th>
                            <th>Odometer (KM)</th>
                            <th style="width: 160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vehicles.length === 0">
                            <td colspan="6" class="text-center text-muted">No trucks found. Add your first truck.</td>
                        </tr>
                        <tr v-for="vehicle in vehicles" :key="vehicle.id">
                            <td>
                                <strong>{{ vehicle.registration_number }}</strong>
                            </td>
                            <td>{{ vehicle.vehicle_type }}</td>
                            <td>{{ formatMakeModel(vehicle) }}</td>
                            <td>
                                <span v-if="vehicle.current_primary_assignment?.driver?.name">
                                    {{ vehicle.current_primary_assignment.driver.name }}
                                </span>
                                <span v-else class="text-muted">Unassigned</span>
                            </td>
                            <td>{{ vehicle.odometer_km ?? '0' }}</td>
                            <td>
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
                                    <AppCrudActionButton
                                        v-if="canManage"
                                        action="edit"
                                        @click="openEdit(vehicle)"
                                    />
                                    <AppCrudActionButton
                                        v-if="canManage"
                                        action="delete"
                                        @click="handleDelete(vehicle)"
                                    />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="pagination.lastPage > 1" class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} trucks
                </small>
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loading || pagination.currentPage <= 1"
                        @click="goToPage(pagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loading || pagination.currentPage >= pagination.lastPage"
                        @click="goToPage(pagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
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
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { createVehicle, deleteVehicle, listVehicles, updateVehicle } from '../../api/vehicles';
import VehicleDocumentsModal from '../../components/trucks/VehicleDocumentsModal.vue';
import VehicleDriverAssignmentModal from '../../components/trucks/VehicleDriverAssignmentModal.vue';
import VehicleFormModal from '../../components/trucks/VehicleFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canManage = computed(() => canInCompany('vehicles.manage'));
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
const fieldErrors = ref({});
const vehicleFormError = ref('');
const showDocuments = ref(false);
const documentsVehicle = ref(null);
const showAssignment = ref(false);
const assignmentVehicle = ref(null);
const flash = reactive({ success: '', error: '' });
const pagination = reactive({
    currentPage: 1,
    perPage: 10,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0,
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
    if (needsCompanySelection()) {
        vehicles.value = [];
        loading.value = false;
        pagination.currentPage = 1;
        pagination.lastPage = 1;
        pagination.total = 0;
        pagination.from = 0;
        pagination.to = 0;

        return;
    }

    loading.value = true;
    flash.error = '';

    try {
        const response = await listVehicles({ page, perPage: pagination.perPage });
        vehicles.value = response.data;
        pagination.currentPage = response.meta.current_page ?? page;
        pagination.lastPage = response.meta.last_page ?? 1;
        pagination.total = response.meta.total ?? vehicles.value.length;
        pagination.from = response.meta.from ?? (vehicles.value.length > 0 ? 1 : 0);
        pagination.to = response.meta.to ?? vehicles.value.length;
    } catch (error) {
        if (!handleCompanyContextError(error, 'Could not load trucks.')) {
            showToast('Could not load trucks.', 'error');
        }

        vehicles.value = [];
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

    loadVehicles(page);
}

function openCreate() {
    formMode.value = 'create';
    editingVehicle.value = null;
    fieldErrors.value = {};
    vehicleFormError.value = '';
    showForm.value = true;
}

function openEdit(vehicle) {
    formMode.value = 'edit';
    editingVehicle.value = { ...vehicle };
    fieldErrors.value = {};
    vehicleFormError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingVehicle.value = null;
    fieldErrors.value = {};
    vehicleFormError.value = '';
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
    fieldErrors.value = {};
    vehicleFormError.value = '';

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
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
            vehicleFormError.value = error.response.data.message ?? 'Please check the highlighted fields.';
            return;
        }

        if (!handleCompanyContextError(error, 'Select a company before saving a truck.')) {
            showToast('Unable to save truck.', 'error');
        }
    } finally {
        saving.value = false;
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

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadVehicles();
    }
});
</script>
