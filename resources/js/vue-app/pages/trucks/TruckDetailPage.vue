<template>
    <div class="container mt-4 truck-detail-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="{ name: 'trucks.index' }" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Trucks
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">{{ vehicle?.registration_number ?? 'Truck Details' }}</h3>
                    <span
                        v-if="vehicle"
                        class="badge"
                        :class="vehicle.is_active ? 'bg-success' : 'bg-secondary'"
                    >
                        {{ vehicle.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p v-if="vehicle" class="text-muted small mb-0">
                    {{ vehicle.vehicle_type_label ?? vehicle.vehicle_type }}
                    <span v-if="vehicle.make || vehicle.model"> · {{ formatMakeModel(vehicle) }}</span>
                </p>
            </div>
            <div v-if="vehicle" class="d-flex flex-wrap gap-2">
                <AppButton
                    v-if="canManage"
                    variant="primary"
                    outline
                    size="sm"
                    icon="bi bi-pencil"
                    label="Edit"
                    @click="openEdit"
                />
                <AppButton
                    v-if="canViewDocuments"
                    variant="secondary"
                    outline
                    size="sm"
                    icon="bi bi-file-earmark-text"
                    label="Documents"
                    @click="openDocuments"
                />
                <AppButton
                    v-if="canViewAssignments"
                    variant="secondary"
                    outline
                    size="sm"
                    icon="bi bi-person-badge"
                    label="Driver"
                    @click="openAssignment"
                />
                <RouterLink
                    v-if="canViewTyres"
                    :to="{ name: 'vehicles.tyres', params: { vehicleId: vehicle.id } }"
                    class="btn btn-outline-success btn-sm"
                >
                    <i class="bi bi-circle"></i> Tyres
                </RouterLink>
            </div>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view truck details.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading truck details…</div>

        <template v-else-if="vehicle">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><strong>Truck Information</strong></div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Registration</span>
                                <div class="fw-semibold">{{ vehicle.registration_number }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Type</span>
                                <div>{{ vehicle.vehicle_type_label ?? vehicle.vehicle_type ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Tyre Layout</span>
                                <div>{{ vehicle.tyre_layout_label ?? vehicle.tyre_layout ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Make</span>
                                <div>{{ vehicle.make || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Model</span>
                                <div>{{ vehicle.model || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Manufacture Year</span>
                                <div>{{ vehicle.manufacture_year || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Truck Running Meter Reading</span>
                                <div class="fw-semibold">{{ formatOdometer(vehicle.odometer_km) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Default Mileage (KM/Ltr)</span>
                                <div>{{ vehicle.default_mileage_km_per_liter ? formatOdometer(vehicle.default_mileage_km_per_liter) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Max Load (Ton)</span>
                                <div>{{ vehicle.max_load_ton ? formatOdometer(vehicle.max_load_ton) : '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Status</span>
                                <div>
                                    <span
                                        class="badge"
                                        :class="vehicle.is_active ? 'bg-success' : 'bg-secondary'"
                                    >
                                        {{ vehicle.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewDocuments" class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Documents</strong>
                            <button
                                v-if="canManageDocuments"
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                @click="openDocuments"
                            >
                                Manage
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="documents.length === 0" class="text-muted text-center py-4">
                                No documents uploaded for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Issue Date</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <th>File</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="doc in documents" :key="doc.id">
                                            <td>{{ doc.document_type_label ?? doc.document_type }}</td>
                                            <td>{{ formatDate(doc.issue_date) }}</td>
                                            <td>{{ formatDate(doc.expiry_date) }}</td>
                                            <td>
                                                <span
                                                    class="badge"
                                                    :class="doc.is_expired ? 'bg-danger' : 'bg-success'"
                                                >
                                                    {{ doc.is_expired ? 'Expired' : 'Valid' }}
                                                </span>
                                            </td>
                                            <td>
                                                <a
                                                    v-if="doc.download_url"
                                                    :href="doc.download_url"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    Download
                                                </a>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewAssignments" class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Driver Assignment History</strong>
                            <button
                                v-if="canManageAssignments"
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                @click="openAssignment"
                            >
                                Manage
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="assignments.length === 0" class="text-muted text-center py-4">
                                No driver assignments recorded for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Driver</th>
                                            <th>Phone</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Primary</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="assignment in assignments" :key="assignment.id">
                                            <td>{{ assignment.driver?.name ?? '—' }}</td>
                                            <td>{{ assignment.driver?.phone ?? '—' }}</td>
                                            <td>{{ formatDate(assignment.assigned_from) }}</td>
                                            <td>{{ formatDate(assignment.assigned_to) }}</td>
                                            <td>
                                                <span
                                                    v-if="assignment.is_primary"
                                                    class="badge bg-primary"
                                                >
                                                    Primary
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewMaintenance" class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Maintenance History</strong>
                            <div class="d-flex gap-2">
                                <RouterLink
                                    :to="{ name: 'maintenance.records', query: { vehicle_id: vehicle.id } }"
                                    class="btn btn-sm btn-outline-secondary"
                                >
                                    View All
                                </RouterLink>
                                <button
                                    v-if="canCreateMaintenance"
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="openMaintenanceCreate"
                                >
                                    Add Service
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="maintenanceLoading" class="text-muted text-center py-4">
                                Loading maintenance history…
                            </div>
                            <div v-else-if="maintenanceRecords.length === 0" class="text-muted text-center py-4">
                                No service records for this truck.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Service Type</th>
                                            <th>Provider</th>
                                            <th class="text-end">Meter Reading</th>
                                            <th class="text-end">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="record in maintenanceRecords" :key="record.id">
                                            <td>{{ formatDate(record.service_date) }}</td>
                                            <td>{{ record.service_type_label ?? record.service_type }}</td>
                                            <td>{{ record.service_provider || '—' }}</td>
                                            <td class="text-end">{{ formatOdometer(record.odometer_km) }}</td>
                                            <td class="text-end">{{ formatMaintenanceCost(record.cost_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Primary Driver</strong></div>
                        <div class="card-body">
                            <template v-if="vehicle.current_primary_assignment?.driver">
                                <div class="fw-semibold">
                                    {{ vehicle.current_primary_assignment.driver.name }}
                                </div>
                                <div v-if="vehicle.current_primary_assignment.driver.phone" class="text-muted small">
                                    {{ vehicle.current_primary_assignment.driver.phone }}
                                </div>
                                <div
                                    v-if="vehicle.current_primary_assignment.assigned_from"
                                    class="small text-muted mt-2"
                                >
                                    Assigned since {{ formatDate(vehicle.current_primary_assignment.assigned_from) }}
                                </div>
                            </template>
                            <div v-else class="text-muted">No primary driver assigned.</div>
                            <button
                                v-if="canManageAssignments"
                                type="button"
                                class="btn btn-sm btn-outline-primary mt-3"
                                @click="openAssignment"
                            >
                                {{ vehicle.current_primary_assignment?.driver ? 'Change Driver' : 'Assign Driver' }}
                            </button>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(vehicle.created_at) }}</div>
                                <div v-if="vehicle.created_by_name" class="text-muted">
                                    by {{ vehicle.created_by_name }}
                                </div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(vehicle.updated_at) }}</div>
                                <div v-if="vehicle.updated_by_name" class="text-muted">
                                    by {{ vehicle.updated_by_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!needsCompanySelection" class="alert alert-danger">
            Truck not found or you do not have access.
        </div>

        <VehicleFormModal
            modal-id="truckDetailFormModal"
            :show="showForm"
            mode="edit"
            :vehicle="vehicle"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="vehicleFormError"
            :can-manage-types="canManage"
            @close="closeForm"
            @submit="handleSubmit"
        />

        <VehicleDocumentsModal
            modal-id="truckDetailDocumentsModal"
            :show="showDocuments"
            :vehicle="vehicle"
            :can-manage="canManageDocuments"
            @close="closeDocuments"
            @saved="loadVehicle"
        />

        <VehicleDriverAssignmentModal
            modal-id="truckDetailAssignmentModal"
            :show="showAssignment"
            :vehicle="vehicle"
            :can-manage="canManageAssignments"
            @close="closeAssignment"
            @assigned="onAssignmentSaved"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { listDriverAssignments } from '../../api/driverAssignments';
import { listVehicleMaintenanceHistory } from '../../api/vehicleMaintenances';
import { fetchVehicle, updateVehicle } from '../../api/vehicles';
import VehicleDocumentsModal from '../../components/trucks/VehicleDocumentsModal.vue';
import VehicleDriverAssignmentModal from '../../components/trucks/VehicleDriverAssignmentModal.vue';
import VehicleFormModal from '../../components/trucks/VehicleFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { useAppToast } from '../../composables/useAppToast';
import { formatDate, formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canManage = computed(() => canInCompany('vehicles.manage'));
const canViewTyres = computed(() => canInCompany('tyres.view'));
const canViewAssignments = computed(() => canInCompany('driver_assignments.view'));
const canManageAssignments = computed(() => canInCompany('driver_assignments.manage'));
const canViewDocuments = computed(() => canInCompany('vehicle_documents.view'));
const canManageDocuments = computed(() => canInCompany('vehicle_documents.manage'));
const canViewMaintenance = computed(() => canInCompany('maintenance.view'));
const canCreateMaintenance = computed(() => canInCompany('maintenance.create'));

const vehicle = ref(null);
const assignments = ref([]);
const maintenanceRecords = ref([]);
const loading = ref(true);
const maintenanceLoading = ref(false);
const saving = ref(false);
const showForm = ref(false);
const showDocuments = ref(false);
const showAssignment = ref(false);
const { fieldErrors, formError: vehicleFormError, clearErrors, applyApiError } = useApiFormErrors();

const documents = computed(() => vehicle.value?.documents ?? []);

function formatMakeModel(item) {
    const parts = [item.make, item.model].filter(Boolean);

    return parts.length ? parts.join(' / ') : '—';
}

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function formatMaintenanceCost(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
}

async function loadAssignments() {
    if (!vehicle.value?.id || !canViewAssignments.value) {
        assignments.value = [];

        return;
    }

    try {
        const response = await listDriverAssignments({
            vehicleId: vehicle.value.id,
            perPage: 100,
        });
        assignments.value = response.data ?? [];
    } catch {
        assignments.value = [];
    }
}

async function loadMaintenanceHistory() {
    if (!vehicle.value?.id || !canViewMaintenance.value) {
        maintenanceRecords.value = [];

        return;
    }

    maintenanceLoading.value = true;

    try {
        const response = await listVehicleMaintenanceHistory(vehicle.value.id, { perPage: 10 });
        maintenanceRecords.value = response.data ?? [];
    } catch {
        maintenanceRecords.value = [];
    } finally {
        maintenanceLoading.value = false;
    }
}

async function loadVehicle() {
    const vehicleId = route.params.vehicleId;

    if (needsCompanySelection.value || !vehicleId) {
        vehicle.value = null;
        assignments.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        vehicle.value = await fetchVehicle(vehicleId);
        await Promise.all([loadAssignments(), loadMaintenanceHistory()]);
    } catch (error) {
        vehicle.value = null;
        assignments.value = [];
        maintenanceRecords.value = [];

        if (!handleCompanyContextError(error, 'Could not load truck details.')) {
            toast.error('Could not load truck details.');
        }
    } finally {
        loading.value = false;
    }
}

function openEdit() {
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    clearErrors();
}

function openDocuments() {
    showDocuments.value = true;
}

function closeDocuments() {
    showDocuments.value = false;
}

function openAssignment() {
    showAssignment.value = true;
}

function closeAssignment() {
    showAssignment.value = false;
}

function openMaintenanceCreate() {
    if (!vehicle.value?.id) {
        return;
    }

    router.push({ name: 'maintenance.create', query: { vehicle_id: vehicle.value.id } });
}

async function onAssignmentSaved() {
    toast.success('Driver assignment saved.');
    await loadVehicle();
}

async function handleSubmit(payload) {
    if (!vehicle.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        vehicle.value = await updateVehicle(vehicle.value.id, payload);
        toast.success('Truck updated successfully.');
        closeForm();
    } catch (error) {
        if (!applyApiError(error, 'Please check the highlighted fields.')) {
            if (!handleCompanyContextError(error, 'Select a company before saving.')) {
                toast.error('Unable to save truck.');
            }
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadVehicle();
    } else {
        loading.value = false;
    }
});

watch(() => route.params.vehicleId, () => {
    loadVehicle();
});
</script>
