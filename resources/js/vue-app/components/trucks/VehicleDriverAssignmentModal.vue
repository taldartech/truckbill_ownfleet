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
                            <form class="row g-3" @submit.prevent="assignDriver">
                                <div class="col-md-6">
                                    <label class="form-label" for="assign_driver_id">Driver *</label>
                                    <select
                                        id="assign_driver_id"
                                        v-model="assignForm.driver_id"
                                        class="form-select"
                                        required
                                    >
                                        <option value="">Select driver</option>
                                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                            {{ driver.name }}
                                            <template v-if="driver.phone"> — {{ driver.phone }}</template>
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="assign_from">Effective From *</label>
                                    <input
                                        id="assign_from"
                                        v-model="assignForm.assigned_from"
                                        type="date"
                                        class="form-control"
                                        required
                                    />
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
import { computed, ref, watch } from 'vue';
import { listAllDrivers } from '../../api/drivers';
import { createDriverAssignment, listDriverAssignments } from '../../api/driverAssignments';
import AppButton from '../ui/AppButton.vue';

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
        drivers.value = await listAllDrivers();
    } catch {
        drivers.value = [];
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
            vehicleLabel.value = props.vehicle?.registration_number ?? '';
            assignForm.value = {
                driver_id: '',
                assigned_from: todayIso(),
            };
            await Promise.all([loadHistory(), loadDrivers()]);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

function close() {
    emit('close');
}

async function assignDriver() {
    if (!props.vehicle?.id || !assignForm.value.driver_id) {
        return;
    }

    saving.value = true;

    try {
        await createDriverAssignment({
            vehicle_id: props.vehicle.id,
            driver_id: assignForm.value.driver_id,
            assigned_from: assignForm.value.assigned_from,
            is_primary: true,
        });
        assignForm.value.driver_id = '';
        await loadHistory();
        emit('assigned');
    } catch {
        window.alert('Could not save driver assignment.');
    } finally {
        saving.value = false;
    }
}
</script>
