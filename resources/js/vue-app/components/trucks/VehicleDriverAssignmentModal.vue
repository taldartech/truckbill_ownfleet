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
                    <div v-if="currentDriverName" class="alert alert-info py-2">
                        <strong>Current primary driver:</strong> {{ currentDriverName }}
                        <span v-if="currentAssignedFrom" class="text-muted">
                            (since {{ currentAssignedFrom }})
                        </span>
                    </div>
                    <div v-else class="alert alert-warning py-2 mb-3">
                        No primary driver assigned for this truck yet.
                    </div>

                    <div v-if="canManage" class="card mb-3">
                        <div class="card-header">Assign Primary Driver</div>
                        <div class="card-body">
                            <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                            <form class="row g-3" @submit.prevent="assignDriver">
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
                                            <button
                                                v-if="assignForm.driver_id"
                                                type="button"
                                                class="btn btn-link driver-clear-btn"
                                                title="Clear selected driver"
                                                @click="clearSelectedDriver"
                                            >
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
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
                                <div class="col-md-6">
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
                                <div class="col-12">
                                    <p class="small text-muted mb-0">
                                        Previous open primary assignment will close automatically the day before this
                                        date.
                                    </p>
                                </div>
                                <div class="col-12">
                                    <AppButton type="submit" icon="bi bi-check-lg" label="Save Assignment" :disabled="saving" />
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
                                        <th>Primary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="assignments.length === 0">
                                        <td colspan="4" class="text-center text-muted">No assignment history.</td>
                                    </tr>
                                    <tr v-for="row in assignments" :key="row.id">
                                        <td>{{ row.driver?.name ?? '—' }}</td>
                                        <td>{{ row.assigned_from ?? '—' }}</td>
                                        <td>{{ row.assigned_to ?? 'Current' }}</td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="row.is_primary ? 'bg-success' : 'bg-secondary'"
                                            >
                                                {{ row.is_primary ? 'Yes' : 'No' }}
                                            </span>
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
import { createDriverAssignment, listDriverAssignments } from '../../api/driverAssignments';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import AppButton from '../ui/AppButton.vue';

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
const assignments = ref([]);
const drivers = ref([]);
const vehicleLabel = ref('');
const driverSearch = ref('');
const activePrimaryByDriver = ref({});
const isDriverDropdownOpen = ref(false);
const driverPickerRef = ref(null);

const assignForm = ref({
    driver_id: '',
    assigned_from: new Date().toISOString().slice(0, 10),
});

const currentDriverName = computed(
    () => props.vehicle?.current_primary_assignment?.driver?.name ?? null,
);

const currentAssignedFrom = computed(
    () => props.vehicle?.current_primary_assignment?.assigned_from ?? null,
);

const unassignedDrivers = computed(() =>
    drivers.value.filter((driver) => !isDriverPrimaryElsewhere(driver.id)),
);

const filteredDrivers = computed(() => {
    const query = driverSearch.value.toLowerCase();

    if (!query) {
        return unassignedDrivers.value;
    }

    return unassignedDrivers.value.filter((driver) => {
        const name = String(driver.name ?? '').toLowerCase();
        const phone = String(driver.phone ?? '').toLowerCase();

        return name.includes(query) || phone.includes(query);
    });
});

const selectedDriverLabel = computed(() => {
    if (!assignForm.value.driver_id) {
        return 'Select driver';
    }

    const selected = unassignedDrivers.value.find((driver) => driver.id === assignForm.value.driver_id);

    if (!selected) {
        return 'Select driver';
    }

    return `${selected.name}${selected.phone ? ` — ${selected.phone}` : ''}`;
});

function todayIso() {
    return new Date().toISOString().slice(0, 10);
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
    try {
        const response = await listDriverAssignments({ perPage: 500 });
        const rows = response.data ?? [];
        const today = todayIso();
        const primaryAssignments = rows.filter(
            (row) =>
                row.is_primary === true &&
                row.assigned_from <= today &&
                (row.assigned_to === null || row.assigned_to >= today),
        );

        activePrimaryByDriver.value = primaryAssignments.reduce((accumulator, row) => {
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
    return Boolean(activePrimaryByDriver.value[driverId]);
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
            assignForm.value = {
                driver_id: '',
                assigned_from: todayIso(),
            };
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

async function assignDriver() {
    if (!props.vehicle?.id || !assignForm.value.driver_id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await createDriverAssignment({
            vehicle_id: props.vehicle.id,
            driver_id: assignForm.value.driver_id,
            assigned_from: assignForm.value.assigned_from,
            is_primary: true,
        });
        assignForm.value.driver_id = '';
        driverSearch.value = '';
        clearErrors();
        await Promise.all([loadHistory(), loadActivePrimaryAssignments()]);
        emit('assigned');
    } catch (error) {
        if (!applyApiError(error)) {
            formError.value = 'Could not save driver assignment. Please try again.';
        }
    } finally {
        saving.value = false;
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

.driver-trigger-wrap {
    position: relative;
}

.driver-clear-btn {
    position: absolute;
    right: 0.4rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    text-decoration: none;
    padding: 0;
    line-height: 1;
}

.driver-clear-btn:hover {
    color: #dc3545;
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
