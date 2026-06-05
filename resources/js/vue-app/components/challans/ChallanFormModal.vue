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
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-receipt'"></i>
                        {{ isEdit ? 'Edit Challan' : 'Add Challan' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_challan_number`" class="form-label">Challan Number *</label>
                                <input
                                    :id="`${modalId}_challan_number`"
                                    v-model="form.challan_number"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.challan_number }"
                                    required
                                />
                                <div v-if="fieldErrors.challan_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.challan_number[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_challan_date`" class="form-label">Date *</label>
                                <input
                                    :id="`${modalId}_challan_date`"
                                    v-model="form.challan_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.challan_date }"
                                    required
                                />
                                <div v-if="fieldErrors.challan_date" class="invalid-feedback d-block">
                                    {{ fieldErrors.challan_date[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_vehicle`" class="form-label">Vehicle *</label>
                                <select
                                    :id="`${modalId}_vehicle`"
                                    v-model="form.vehicle_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldErrors.vehicle_id }"
                                    required
                                >
                                    <option value="">Select vehicle</option>
                                    <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                                        {{ vehicle.registration_number }}
                                    </option>
                                </select>
                                <div v-if="fieldErrors.vehicle_id" class="invalid-feedback d-block">
                                    {{ fieldErrors.vehicle_id[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_driver`" class="form-label">Driver *</label>
                                <select
                                    :id="`${modalId}_driver`"
                                    v-model="form.driver_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldErrors.driver_id }"
                                    required
                                >
                                    <option value="">Select driver</option>
                                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                        {{ driver.name }}
                                    </option>
                                </select>
                                <div v-if="fieldErrors.driver_id" class="invalid-feedback d-block">
                                    {{ fieldErrors.driver_id[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_violation_type`" class="form-label">Violation Type *</label>
                                <input
                                    :id="`${modalId}_violation_type`"
                                    v-model="form.violation_type"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.violation_type }"
                                    placeholder="e.g. Over speeding"
                                    required
                                />
                                <div v-if="fieldErrors.violation_type" class="invalid-feedback d-block">
                                    {{ fieldErrors.violation_type[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_amount`" class="form-label">Amount (₹) *</label>
                                <input
                                    :id="`${modalId}_amount`"
                                    v-model="form.amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.amount }"
                                    required
                                />
                                <div v-if="fieldErrors.amount" class="invalid-feedback d-block">
                                    {{ fieldErrors.amount[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_status`" class="form-label">Payment Status *</label>
                                <select
                                    :id="`${modalId}_status`"
                                    v-model="form.status"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldErrors.status }"
                                    required
                                >
                                    <option value="UNPAID">Unpaid</option>
                                    <option value="PAID">Paid</option>
                                </select>
                                <div v-if="fieldErrors.status" class="invalid-feedback d-block">
                                    {{ fieldErrors.status[0] }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-success" :disabled="saving">
                            <i class="bi bi-check-circle"></i>
                            {{
                                saving
                                    ? isEdit
                                        ? 'Updating...'
                                        : 'Adding...'
                                    : isEdit
                                      ? 'Update Challan'
                                      : 'Add Challan'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { listAllDrivers } from '../../api/drivers';
import { listAllVehicles } from '../../api/vehicles';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    challan: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let bootstrapModal = null;

const isEdit = computed(() => props.mode === 'edit');

const vehicles = ref([]);
const drivers = ref([]);

const form = ref(emptyForm());

function emptyForm() {
    return {
        challan_number: '',
        challan_date: '',
        vehicle_id: '',
        driver_id: '',
        violation_type: '',
        amount: '',
        status: 'UNPAID',
    };
}

function fillForm(challan) {
    form.value = {
        challan_number: challan?.challan_number ?? '',
        challan_date: challan?.challan_date ?? '',
        vehicle_id: challan?.vehicle_id ?? '',
        driver_id: challan?.driver_id ?? '',
        violation_type: challan?.violation_type ?? '',
        amount: challan?.amount !== null && challan?.amount !== undefined ? String(challan.amount) : '',
        status: challan?.status ?? 'UNPAID',
    };
}

async function loadOptions() {
    const [vehicleList, driverList] = await Promise.all([listAllVehicles(), listAllDrivers({ activeOnly: true })]);

    vehicles.value = vehicleList;
    drivers.value = driverList;
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
            await loadOptions();
            fillForm(props.challan);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

watch(
    () => props.challan,
    (challan) => {
        if (props.show) {
            fillForm(challan);
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    const amount = form.value.amount === '' ? Number.NaN : Number(form.value.amount);

    if (Number.isNaN(amount) || amount < 0) {
        return;
    }

    emit('submit', {
        challan_number: form.value.challan_number.trim(),
        challan_date: form.value.challan_date,
        vehicle_id: form.value.vehicle_id,
        driver_id: form.value.driver_id,
        violation_type: form.value.violation_type.trim(),
        amount,
        status: form.value.status,
    });
}
</script>
