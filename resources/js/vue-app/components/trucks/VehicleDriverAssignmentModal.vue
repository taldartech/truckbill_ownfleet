<template>
    <AppModal
        :show="show"
        size="lg"
        :loading="modalLoading"
        :form-error="assignmentErrorMessage"
        :dismissible="true"
        @close="close"
    >
        <template #title>
            <i class="bi bi-person-badge"></i>
            Driver Assignment — {{ vehicleLabel }}
        </template>

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
                                    <SearchableSelect
                                        input-id="assign_driver_id"
                                        v-model="assignForm.driver_id"
                                        :items="driverSelectOptions"
                                        placeholder="Select driver"
                                        search-placeholder="Search driver by name or mobile"
                                        empty-message="No unassigned driver found."
                                        :invalid="hasFieldError(fieldErrors, 'driver_id')"
                                        :error-message="fieldErrorMessage(fieldErrors, 'driver_id')"
                                        :plain="false"
                                    />
                                </div>
                                <div :class="editingClosedAssignment ? 'col-md-4' : 'col-md-6'">
                                    <label class="form-label" for="assign_from">Effective From *</label>
                                    <AppDateInput
                                        input-id="assign_from"
                                        v-model="assignForm.assigned_from"
                                        :min="minimumAssignedFrom"
                                        :invalid="
                                            hasFieldError(fieldErrors, 'assigned_from') ||
                                            hasFieldError(fieldErrors, 'vehicle_id') ||
                                            Boolean(assignedFromClientError)
                                        "
                                        required
                                    />
                                    <div
                                        v-if="
                                            fieldErrorMessage(fieldErrors, 'assigned_from') ||
                                            fieldErrorMessage(fieldErrors, 'vehicle_id') ||
                                            assignedFromClientError
                                        "
                                        class="invalid-feedback d-block"
                                    >
                                        {{
                                            fieldErrorMessage(fieldErrors, 'assigned_from') ||
                                            fieldErrorMessage(fieldErrors, 'vehicle_id') ||
                                            assignedFromClientError
                                        }}
                                    </div>
                                    <div
                                        v-else-if="minimumAssignedFrom"
                                        class="form-text"
                                    >
                                        Driver joined on {{ formatJoiningDate(minimumAssignedFrom) }}.
                                        Effective from cannot be earlier.
                                    </div>
                                </div>
                                <div v-if="editingClosedAssignment" class="col-md-4">
                                    <label class="form-label" for="assign_to">Effective To</label>
                                    <AppDateInput
                                        input-id="assign_to"
                                        v-model="assignForm.assigned_to"
                                        :invalid="hasFieldError(fieldErrors, 'assigned_to')"
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
                                <div class="col-12 d-flex justify-content-end">
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
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
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
import { formatDate, toDateInputValue } from '../../utils/datetime';
import AppButton from '../ui/AppButton.vue';
import AppCrudActionButton from '../ui/AppCrudActionButton.vue';
import AppModal from '../ui/AppModal.vue';
import AppTableActions from '../ui/AppTableActions.vue';
import SearchableSelect from '../ui/SearchableSelect.vue';

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

const props = defineProps({
    modalId: { type: String, default: 'vehicleDriverAssignmentModal' },
    show: { type: Boolean, default: false },
    vehicle: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'assigned']);

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
const activePrimaryByDriver = ref({});
const assignedFromClientError = ref('');

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

const modalLoading = computed(() => saving.value || removing.value || deletingId.value !== null);

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

const selectedDriver = computed(
    () => drivers.value.find((driver) => driver.id === assignForm.value.driver_id) ?? null,
);

const minimumAssignedFrom = computed(() => selectedDriver.value?.joining_date ?? undefined);

const driverSelectOptions = computed(() =>
    selectableDrivers.value.map((driver) => ({
        id: driver.id,
        label: `${driver.name}${driver.phone ? ` — ${driver.phone}` : ''}`,
        hint: driver.joining_date ? `Joined ${formatJoiningDate(driver.joining_date)}` : '',
        searchText: `${driver.name} ${driver.phone ?? ''}`.trim(),
    })),
);

function formatJoiningDate(value) {
    return formatDate(value, value ?? '—');
}

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
    assignedFromClientError.value = '';
    assignForm.value = {
        driver_id: '',
        assigned_from: asOfDate.value || toDateInputValue(),
        assigned_to: '',
    };
}

function cancelEdit() {
    assignedFromClientError.value = '';
    clearErrors();
    resetAssignForm();
}

function startEdit(row) {
    if (row.has_paid_attendance) {
        return;
    }

    clearErrors();
    assignedFromClientError.value = '';
    editingAssignment.value = row;
    assignForm.value = {
        driver_id: row.driver_id ?? '',
        assigned_from: row.assigned_from ?? '',
        assigned_to: row.assigned_to ?? '',
    };
}

async function refreshAssignmentState() {
    await loadPrimaryAssignment();
    await Promise.all([loadHistory(), loadActivePrimaryAssignments()]);
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            clearErrors();
            vehicleLabel.value = props.vehicle?.registration_number ?? '';
            await loadPrimaryAssignment();
            resetAssignForm();
            await Promise.all([loadHistory(), loadDrivers(), loadActivePrimaryAssignments()]);
        }
    },
);

watch(
    () => assignForm.value.driver_id,
    (driverId) => {
        if (!driverId) {
            return;
        }

        assignedFromClientError.value = '';
        const driver = drivers.value.find((item) => item.id === driverId);

        if (driver?.joining_date && assignForm.value.assigned_from < driver.joining_date) {
            assignForm.value.assigned_from = driver.joining_date;
        }
    },
);

function close() {
    emit('close');
}

async function saveAssignment() {
    if (!props.vehicle?.id || !assignForm.value.driver_id) {
        return;
    }

    assignedFromClientError.value = '';

    const joiningDate = selectedDriver.value?.joining_date;

    if (joiningDate && assignForm.value.assigned_from < joiningDate) {
        assignedFromClientError.value = `Effective from cannot be earlier than the driver's joining date (${formatJoiningDate(joiningDate)}).`;

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
