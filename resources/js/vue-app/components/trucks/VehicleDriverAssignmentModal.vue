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
                            v-if="canEdit"
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

                    <div v-if="(editingAssignment && canEdit) || (!editingAssignment && canCreate)" class="card mb-3">
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
                                <div class="col-12">
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
                                <div class="col-md-6">
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
                                <div class="col-md-6">
                                    <label class="form-label" for="assign_to">Effective To</label>
                                    <AppDateInput
                                        input-id="assign_to"
                                        v-model="assignForm.assigned_to"
                                        :max="selectedDriver?.availability?.available_until ?? undefined"
                                        :invalid="hasFieldError(fieldErrors, 'assigned_to')"
                                    />
                                    <div
                                        v-if="fieldErrorMessage(fieldErrors, 'assigned_to')"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ fieldErrorMessage(fieldErrors, 'assigned_to') }}
                                    </div>
                                    <div
                                        v-if="selectedDriver?.availability?.available_until"
                                        class="form-text text-warning"
                                    >
                                        Driver is available until {{ formatJoiningDate(selectedDriver.availability.available_until) }} due to another scheduled assignment.
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
                                        <th>Status</th>
                                        <th v-if="canEdit" class="app-index-actions-cell" style="width: 120px">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="assignments.length === 0">
                                        <td :colspan="canEdit ? 5 : 4" class="text-center text-muted">
                                            No assignment history.
                                        </td>
                                    </tr>
                                    <tr v-for="row in assignments" :key="row.id">
                                        <td>{{ row.driver?.name ?? '—' }}</td>
                                        <td>{{ row.assigned_from ?? '—' }}</td>
                                        <td>{{ assignmentToLabel(row) }}</td>
                                        <td>{{ assignmentStatusLabel(row) }}</td>
                                        <td v-if="canEdit" class="app-index-actions-cell">
                                            <AppTableActions>
                                                <AppCrudActionButton
                                                    action="edit"
                                                    :disabled="deletingId === row.id"
                                                    title="Edit assignment"
                                                    @click="startEdit(row)"
                                                />
                                                <AppCrudActionButton
                                                    v-if="!isActivePrimaryRow(row)"
                                                    action="delete"
                                                    :disabled="deletingId === row.id"
                                                    title="Delete assignment"
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
    canCreate: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
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
const assignedFromClientError = ref('');

const assignForm = ref({
    driver_id: '',
    assigned_from: '',
    assigned_to: '',
});



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

const selectableDrivers = computed(() => drivers.value);

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

function assignmentStatusLabel(row) {
    return isActivePrimaryRow(row) ? 'Current' : 'Completed';
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
        const date = assignForm.value.assigned_from || asOfDate.value || null;
        drivers.value = await listAllDrivers({
            activeOnly: true,
            availableOnDate: date,
            excludeVehicleId: props.vehicle?.id || null,
            excludeAssignmentId: editingAssignment.value?.id || null,
        });

        if (assignForm.value.driver_id) {
            const selectedDriverObj = drivers.value.find((d) => d.id === assignForm.value.driver_id);
            if (!selectedDriverObj) {
                assignForm.value.driver_id = '';
                assignForm.value.assigned_to = '';
            } else if (selectedDriverObj.availability?.available_until) {
                const limit = selectedDriverObj.availability.available_until;
                if (!editingAssignment.value) {
                    assignForm.value.assigned_to = limit;
                } else if (assignForm.value.assigned_to > limit) {
                    assignForm.value.assigned_to = limit;
                }
            } else if (!editingAssignment.value) {
                assignForm.value.assigned_to = '';
            }
        }
    } catch {
        drivers.value = [];
    }
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
    await loadHistory();
    await loadDrivers();
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            clearErrors();
            vehicleLabel.value = props.vehicle?.registration_number ?? '';
            await loadPrimaryAssignment();
            resetAssignForm();
            await loadHistory();
        }
    },
);

watch(
    () => assignForm.value.assigned_from,
    async (newDate, oldDate) => {
        if (props.show && newDate && newDate !== oldDate) {
            await loadDrivers();
        }
    }
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

        if (!editingAssignment.value) {
            if (driver?.availability?.available_until) {
                assignForm.value.assigned_to = driver.availability.available_until;
            } else {
                assignForm.value.assigned_to = '';
            }
        } else {
            if (driver?.availability?.available_until && assignForm.value.assigned_to > driver.availability.available_until) {
                assignForm.value.assigned_to = driver.availability.available_until;
            }
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
        assigned_to: assignForm.value.assigned_to || null,
        is_primary: true,
    };

    try {
        if (editingAssignment.value) {
            await updateDriverAssignment(editingAssignment.value.id, payload);
        } else {
            await createDriverAssignment(payload);
        }

        clearErrors();
        resetAssignForm();
        await refreshAssignmentState();
        emit('assigned', 'save');
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
    if (!row?.id) {
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
        emit('assigned', 'delete');
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
        emit('assigned', 'remove');
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = 'Could not remove primary driver. Please try again.';
        }
    } finally {
        removing.value = false;
    }
}
</script>
