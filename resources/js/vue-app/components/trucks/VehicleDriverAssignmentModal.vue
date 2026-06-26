<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i class="bi bi-person-badge"></i>
                        Driver Assignment — {{ vehicleLabel }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="assignmentErrorMessage" class="alert alert-danger py-2">{{ assignmentErrorMessage }}</div>
                    <div v-if="hasActivePrimary" class="alert alert-info py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <strong>Current driver:</strong> {{ currentDriverName }}
                            <span v-if="currentAssignedFrom" class="text-muted">
                                (since {{ currentAssignedFrom }})
                            </span>
                        </div>
                        <button
                            v-if="canManage"
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            :disabled="removing"
                            @click="removePrimaryDriver"
                        >
                            <span v-if="removing" class="spinner-border spinner-border-sm me-1" role="status" />
                            Remove Driver
                        </button>
                    </div>
                    <div v-else class="alert alert-warning py-2 mb-3">
                        No driver assigned for this truck yet.
                    </div>

                    <div v-if="canManage" class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>{{ editingAssignment ? 'Edit Assignment' : 'Assign New Driver' }}</span>
                            <button
                                v-if="editingAssignment"
                                type="button"
                                class="btn btn-sm btn-link text-decoration-none"
                                @click="cancelEdit"
                            >
                                Cancel edit
                            </button>
                        </div>
                        <div class="card-body">
                            <form class="row g-3" @submit.prevent="saveAssignment">
                                <div class="col-md-6">
                                    <label class="form-label" for="assign_driver_id">Driver *</label>
                                    <div
                                        ref="driverPickerRef"
                                        class="driver-picker"
                                        :class="{ 'is-invalid-picker': hasFieldError(fieldErrors, 'driver_id') }"
                                    >
                                        <div class="driver-trigger-wrap">
                                            <button
                                                id="assign_driver_id"
                                                type="button"
                                                class="form-select text-start driver-trigger"
                                                @click="toggleDriverDropdown"
                                            >
                                                {{ selectedDriverLabel }}
                                            </button>
                                            <div v-if="assignForm.driver_id" class="driver-trigger-icons">
                                                <button
                                                    type="button"
                                                    class="btn btn-link driver-clear-btn"
                                                    title="Clear selected driver"
                                                    @click.stop="clearSelectedDriver"
                                                >
                                                    <i class="bi bi-x-circle-fill"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div v-if="isDriverDropdownOpen" class="driver-dropdown-menu shadow-sm">
                                            <div class="driver-search-wrap">
                                                <input
                                                    v-model.trim="driverSearch"
                                                    type="text"
                                                    class="form-control form-control-sm driver-search-input"
                                                    placeholder="Search driver by name or mobile"
                                                />
                                            </div>
                                            <div class="driver-list-scroll">
                                                <button
                                                    v-for="driver in filteredDrivers"
                                                    :key="driver.id"
                                                    type="button"
                                                    class="dropdown-item driver-option"
                                                    @click="selectDriver(driver)"
                                                >
                                                    {{ driver.name }}
                                                    <template v-if="driver.phone"> — {{ driver.phone }}</template>
                                                </button>
                                                <div v-if="filteredDrivers.length === 0" class="px-3 py-2 text-muted small">
                                                    No unassigned driver found.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input v-model="assignForm.driver_id" type="hidden" required />
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'driver_id')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'driver_id') }}
                                    </div>
                                </div>
                                <div :class="editingClosedAssignment ? 'col-md-4' : 'col-md-6'">
                                    <label class="form-label" for="assign_from">Effective From *</label>
                                    <input
                                        id="assign_from"
                                        v-model="assignForm.assigned_from"
                                        type="date"
                                        class="form-control"
                                        :class="{
                                            'is-invalid':
                                                hasFieldError(fieldErrors, 'assigned_from') ||
                                                hasFieldError(fieldErrors, 'vehicle_id'),
                                        }"
                                        required
                                    />
                                    <div
                                        v-if="
                                            fieldErrorMessage(fieldErrors, 'assigned_from') ||
                                            fieldErrorMessage(fieldErrors, 'vehicle_id')
                                        "
                                        class="invalid-feedback d-block"
                                    >
                                        {{
                                            fieldErrorMessage(fieldErrors, 'assigned_from') ||
                                            fieldErrorMessage(fieldErrors, 'vehicle_id')
                                        }}
                                    </div>
                                </div>
                                <div v-if="editingClosedAssignment" class="col-md-4">
                                    <label class="form-label" for="assign_to">Effective To</label>
                                    <input
                                        id="assign_to"
                                        v-model="assignForm.assigned_to"
                                        type="date"
                                        class="form-control"
                                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'assigned_to') }"
                                    />
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'assigned_to')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'assigned_to') }}
                                    </div>
                                </div>
                                <div v-if="!editingAssignment" class="col-12">
                                    <p class="small text-muted mb-0">
                                        The current primary driver on this truck will be closed automatically one day
                                        before the new effective date.
                                    </p>
                                </div>
                                <div class="col-12">
                                    <AppButton
                                        type="submit"
                                        icon="bi bi-check-lg"
                                        :label="editingAssignment ? 'Save Changes' : 'Save Assignment'"
                                        :disabled="saving"
                                    />
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Assignment History</div>
                        <div class="card-body table-responsive p-0">
                            <div v-if="loading" class="text-center py-3 text-muted">Loading history…</div>
                            <table v-else class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th v-if="canManage" class="app-index-actions-cell" style="width: 100px">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="assignments.length === 0">
                                        <td :colspan="canManage ? 4 : 3" class="text-center text-muted">
                                            No assignment history.
                                        </td>
                                    </tr>
                                    <tr v-for="row in assignments" :key="row.id">
                                        <td>{{ row.driver?.name ?? '—' }}</td>
                                        <td>{{ row.assigned_from ?? '—' }}</td>
                                        <td>{{ assignmentToLabel(row) }}</td>
                                        <td v-if="canManage" class="app-index-actions-cell">
                                            <AppTableActions>
                                                <AppCrudActionButton
                                                    action="edit"
                                                    :disabled="row.has_paid_attendance || deletingId === row.id"
                                                    :title="
                                                        row.has_paid_attendance
                                                            ? row.is_period_locked
                                                                ? 'Cannot edit — payroll month is closed or locked'
                                                                : 'Cannot edit — wage payment recorded in attendance'
                                                            : 'Edit assignment'
                                                    "
                                                    @click="startEdit(row)"
                                                />
                                                <AppCrudActionButton
                                                    action="delete"
                                                    :disabled="row.has_paid_attendance || deletingId === row.id"
                                                    :title="
                                                        row.has_paid_attendance
                                                            ? row.is_period_locked
                                                                ? 'Cannot delete — payroll month is closed or locked'
                                                                : 'Cannot delete — wage payment recorded in attendance'
                                                            : 'Delete assignment'
                                                    "
                                                    @click="deleteAssignment(row)"
                                                />
                                            </AppTableActions>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { listAllDrivers } from '../../api/drivers';
import {
    createDriverAssignment,
    deleteDriverAssignment,
    listDriverAssignments,
    removeVehiclePrimaryDriver,
    updateDriverAssignment,
} from '../../api/driverAssignments';
import { fetchVehiclePrimaryAssignment } from '../../api/vehicles';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import { toDateInputValue } from '../../utils/datetime';
import AppButton from '../ui/AppButton.vue';
import AppCrudActionButton from '../ui/AppCrudActionButton.vue';
import AppTableActions from '../ui/AppTableActions.vue';

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const props = defineProps({
    modalId: { type: String, default: 'vehicleDriverAssignmentModal' },
    show: { type: Boolean, default: false },
    vehicle: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'assigned']);

const modalElement = ref(null);
let bootstrapModal = null;

const loading = ref(false);
const saving = ref(false);
const removing = ref(false);
const deletingId = ref(null);
const editingAssignment = ref(null);
const assignments = ref([]);
const primarySummary = ref({
    as_of_date: '',
    has_active_primary: false,
    assignment: null,
});
const drivers = ref([]);
const vehicleLabel = ref('');
const driverSearch = ref('');
const activePrimaryByDriver = ref({});
const isDriverDropdownOpen = ref(false);
const driverPickerRef = ref(null);

const assignForm = ref({
    driver_id: '',
    assigned_from: '',
    assigned_to: '',
});

const editingClosedAssignment = computed(
    () => editingAssignment.value !== null && Boolean(editingAssignment.value.assigned_to),
);

const asOfDate = computed(() => primarySummary.value.as_of_date || '');

const hasActivePrimary = computed(() => Boolean(primarySummary.value.has_active_primary));

const currentDriverName = computed(() => primarySummary.value.assignment?.driver?.name ?? null);

const currentAssignedFrom = computed(() => primarySummary.value.assignment?.assigned_from ?? null);

const assignmentErrorMessage = computed(
    () =>
        fieldErrorMessage(fieldErrors.value, 'driver_assignment')
        || fieldErrorMessage(fieldErrors.value, 'assigned_from')
        || formError.value
        || '',
);

const unassignedDrivers = computed(() =>
    drivers.value.filter((driver) => !isDriverPrimaryElsewhere(driver.id)),
);

const selectableDrivers = computed(() => {
    const editingDriverId = editingAssignment.value?.driver_id;

    if (!editingDriverId) {
        return unassignedDrivers.value;
    }

    return drivers.value.filter(
        (driver) => !isDriverPrimaryElsewhere(driver.id) || driver.id === editingDriverId,
    );
});

const filteredDrivers = computed(() => {
    const query = driverSearch.value.toLowerCase();

    if (!query) {
        return selectableDrivers.value;
    }

    return selectableDrivers.value.filter((driver) => {
        const name = String(driver.name ?? '').toLowerCase();
        const phone = String(driver.phone ?? '').toLowerCase();

        return name.includes(query) || phone.includes(query);
    });
});

const selectedDriverLabel = computed(() => {
    if (!assignForm.value.driver_id) {
        return 'Select driver';
    }

    const selected = selectableDrivers.value.find((driver) => driver.id === assignForm.value.driver_id);

    if (!selected) {
        return 'Select driver';
    }

    return `${selected.name}${selected.phone ? ` — ${selected.phone}` : ''}`;
});

function assignmentToLabel(row) {
    if (isActivePrimaryRow(row)) {
        return 'Current';
    }

    return row.assigned_to ?? '—';
}

function isActivePrimaryRow(row) {
    const date = asOfDate.value;

    if (!date || !row?.is_primary) {
        return false;
    }

    const from = String(row.assigned_from);
    const to = row.assigned_to ? String(row.assigned_to) : null;

    return from <= date && (to === null || to > date);
}

async function loadPrimaryAssignment() {
    if (!props.vehicle?.id) {
        primarySummary.value = {
            as_of_date: '',
            has_active_primary: false,
            assignment: null,
        };

        return;
    }

    try {
        primarySummary.value = await fetchVehiclePrimaryAssignment(props.vehicle.id);
    } catch {
        primarySummary.value = {
            as_of_date: '',
            has_active_primary: false,
            assignment: null,
        };
    }
}

async function loadHistory() {
    if (!props.vehicle?.id) {
        return;
    }

    loading.value = true;

    try {
        const response = await listDriverAssignments({ vehicleId: props.vehicle.id, perPage: 100 });
        assignments.value = response.data ?? [];
    } catch {
        assignments.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadDrivers() {
    try {
        drivers.value = await listAllDrivers({ activeOnly: true });
    } catch {
        drivers.value = [];
    }
}

async function loadActivePrimaryAssignments() {
    const date = asOfDate.value;

    if (!date) {
        activePrimaryByDriver.value = {};

        return;
    }

    try {
        const response = await listDriverAssignments({ perPage: 500 });
        const rows = response.data ?? [];

        activePrimaryByDriver.value = rows
            .filter((row) => isActivePrimaryRow(row))
            .reduce((accumulator, row) => {
                if (!row.driver_id) {
                    return accumulator;
                }

                accumulator[row.driver_id] = {
                    vehicleId: row.vehicle_id,
                    vehicleLabel: row.vehicle?.registration_number ?? 'another truck',
                    driverName: row.driver?.name ?? 'Driver',
                };

                return accumulator;
            }, {});
    } catch {
        activePrimaryByDriver.value = {};
    }
}

function isDriverPrimaryElsewhere(driverId) {
    const conflict = activePrimaryByDriver.value[driverId];

    if (!conflict) {
        return false;
    }

    if (editingAssignment.value && conflict.vehicleId === props.vehicle?.id) {
        return false;
    }

    return true;
}

function resetAssignForm() {
    editingAssignment.value = null;
    assignForm.value = {
        driver_id: '',
        assigned_from: asOfDate.value || toDateInputValue(),
        assigned_to: '',
    };
}

function cancelEdit() {
    driverSearch.value = '';
    isDriverDropdownOpen.value = false;
    clearErrors();
    resetAssignForm();
}

function startEdit(row) {
    if (row.has_paid_attendance) {
        return;
    }

    clearErrors();
    editingAssignment.value = row;
    assignForm.value = {
        driver_id: row.driver_id ?? '',
        assigned_from: row.assigned_from ?? '',
        assigned_to: row.assigned_to ?? '',
    };
    driverSearch.value = '';
    isDriverDropdownOpen.value = false;
}

function toggleDriverDropdown() {
    isDriverDropdownOpen.value = !isDriverDropdownOpen.value;
}

function selectDriver(driver) {
    assignForm.value.driver_id = driver.id;
    isDriverDropdownOpen.value = false;
}

function clearSelectedDriver() {
    assignForm.value.driver_id = '';
    driverSearch.value = '';
}

function handleClickOutside(event) {
    if (driverPickerRef.value && !driverPickerRef.value.contains(event.target)) {
        isDriverDropdownOpen.value = false;
    }
}

async function refreshAssignmentState() {
    await loadPrimaryAssignment();
    await Promise.all([loadHistory(), loadActivePrimaryAssignments()]);
}

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false });
        }

        if (visible) {
            clearErrors();
            vehicleLabel.value = props.vehicle?.registration_number ?? '';
            driverSearch.value = '';
            isDriverDropdownOpen.value = false;
            await loadPrimaryAssignment();
            resetAssignForm();
            await Promise.all([loadHistory(), loadDrivers(), loadActivePrimaryAssignments()]);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

function close() {
    emit('close');
}

async function saveAssignment() {
    if (!props.vehicle?.id || !assignForm.value.driver_id) {
        return;
    }

    saving.value = true;
    clearErrors();

    const payload = {
        vehicle_id: props.vehicle.id,
        driver_id: assignForm.value.driver_id,
        assigned_from: assignForm.value.assigned_from,
        is_primary: true,
    };

    if (editingClosedAssignment.value) {
        payload.assigned_to = assignForm.value.assigned_to || null;
    }

    try {
        if (editingAssignment.value) {
            await updateDriverAssignment(editingAssignment.value.id, payload);
        } else {
            await createDriverAssignment(payload);
        }

        driverSearch.value = '';
        clearErrors();
        resetAssignForm();
        await refreshAssignmentState();
        emit('assigned');
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = editingAssignment.value
                ? 'Could not update driver assignment. Please try again.'
                : 'Could not save driver assignment. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

async function deleteAssignment(row) {
    if (!row?.id || row.has_paid_attendance) {
        return;
    }

    const driverName = row.driver?.name ?? 'this driver';
    const confirmed = window.confirm(`Delete assignment for ${driverName}? This cannot be undone.`);

    if (!confirmed) {
        return;
    }

    deletingId.value = row.id;
    clearErrors();

    try {
        await deleteDriverAssignment(row.id);

        if (editingAssignment.value?.id === row.id) {
            resetAssignForm();
        }

        await refreshAssignmentState();
        emit('assigned');
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = 'Could not delete driver assignment. Please try again.';
        }
    } finally {
        deletingId.value = null;
    }
}

async function removePrimaryDriver() {
    if (!props.vehicle?.id || !hasActivePrimary.value) {
        return;
    }

    const confirmed = window.confirm(
        `Remove ${currentDriverName.value} as driver from ${vehicleLabel.value}?`,
    );

    if (!confirmed) {
        return;
    }

    removing.value = true;
    clearErrors();

    try {
        const response = await removeVehiclePrimaryDriver(props.vehicle.id);
        primarySummary.value = {
            as_of_date: response.as_of_date ?? asOfDate.value,
            has_active_primary: Boolean(response.has_active_primary),
            assignment: response.assignment ?? null,
        };
        await refreshAssignmentState();
        emit('assigned');
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = 'Could not remove primary driver. Please try again.';
        }
    } finally {
        removing.value = false;
    }
}
</script>

<style scoped>
.driver-picker {
    position: relative;
}

.is-invalid-picker .driver-trigger {
    border-color: #dc3545;
}

.driver-trigger {
    padding-right: 2.4rem;
}

.driver-trigger-wrap:has(.driver-trigger-icons) .driver-trigger {
    padding-right: 3.6rem;
}

.driver-trigger-wrap {
    position: relative;
}

.driver-trigger-icons {
    position: absolute;
    top: 0;
    right: 2rem;
    bottom: 0;
    display: flex;
    align-items: center;
    pointer-events: none;
}

.driver-clear-btn {
    position: static;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: auto;
    color: #6c757d;
    text-decoration: none;
    padding: 0;
    line-height: 1;
    border: none;
}

.driver-clear-btn:hover,
.driver-clear-btn:focus {
    color: #dc3545;
    text-decoration: none;
}

.driver-dropdown-menu {
    position: absolute;
    z-index: 1060;
    width: 100%;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    margin-top: 0.45rem;
}

.driver-search-wrap {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #fff;
    padding: 0.55rem;
    border-bottom: 1px solid #dee2e6;
}

.driver-search-input {
    height: 2rem;
}

.driver-list-scroll {
    max-height: 220px;
    overflow-y: auto;
    padding: 0.2rem 0;
}

.driver-option {
    white-space: normal;
    line-height: 1.25;
    padding-top: 0.4rem;
    padding-bottom: 0.4rem;
}
</style>
