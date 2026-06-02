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
                        <i class="bi bi-calendar-check"></i>
                        {{ isEdit ? 'Edit Attendance' : 'Mark Attendance' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_date`" class="form-label">Date *</label>
                                <input
                                    :id="`${modalId}_date`"
                                    v-model="form.attendance_date"
                                    type="date"
                                    class="form-control"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_vehicle`" class="form-label">Vehicle *</label>
                                <select
                                    :id="`${modalId}_vehicle`"
                                    v-model="form.vehicle_id"
                                    class="form-select"
                                    required
                                    @change="onVehicleChange"
                                >
                                    <option value="">Select vehicle</option>
                                    <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                                        {{ vehicle.registration_number }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_primary`" class="form-label">Primary Driver *</label>
                                <select
                                    :id="`${modalId}_primary`"
                                    v-model="form.primary_driver_id"
                                    class="form-select"
                                    required
                                    @change="applyAutoWageFromPrimaryDriver"
                                >
                                    <option value="">Select driver</option>
                                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                        {{ driver.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_status`" class="form-label">Status *</label>
                                <select
                                    :id="`${modalId}_status`"
                                    v-model="form.status"
                                    class="form-select"
                                    required
                                    @change="applyAutoWageFromPrimaryDriver"
                                >
                                    <option value="PRESENT">Present</option>
                                    <option value="ABSENT">Absent</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="form.status === 'ABSENT'" class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_temp`" class="form-label">Alternate Driver</label>
                                <select
                                    :id="`${modalId}_temp`"
                                    v-model="form.temporary_driver_id"
                                    class="form-select"
                                    @change="applyAutoWageFromPrimaryDriver"
                                >
                                    <option value="">None</option>
                                    <option
                                        v-for="driver in alternateDrivers"
                                        :key="driver.id"
                                        :value="driver.id"
                                    >
                                        {{ driver.name }}
                                    </option>
                                </select>
                                <small class="text-muted">Wage still credits to primary driver.</small>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_remarks`" class="form-label">Assignment Remarks</label>
                                <input
                                    :id="`${modalId}_remarks`"
                                    v-model="form.temporary_assignment_remarks"
                                    type="text"
                                    class="form-control"
                                    placeholder="Reason for alternate driver"
                                />
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_wage`" class="form-label">Daily Wage Amount</label>
                                <input
                                    :id="`${modalId}_wage`"
                                    v-model="form.wage_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    placeholder="Auto from primary driver if empty"
                                    @input="wageAmountTouched = true"
                                />
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_payment_status`" class="form-label">Payment Status</label>
                                <input
                                    :id="`${modalId}_payment_status`"
                                    type="text"
                                    class="form-control"
                                    :value="paymentStatusLabel"
                                    readonly
                                />
                                <small v-if="isWagePayable" class="text-muted">New attendance defaults to unpaid.</small>
                                <small v-else class="text-muted">No wage due — payment not required.</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label :for="`${modalId}_notes`" class="form-label">Wage Transfer Notes</label>
                                <input
                                    :id="`${modalId}_notes`"
                                    v-model="form.wage_transfer_notes"
                                    type="text"
                                    class="form-control"
                                    placeholder="Payment handoff notes"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Saving...' : isEdit ? 'Update' : 'Save Attendance' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    attendance: { type: Object, default: null },
    vehicles: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let bootstrapModal = null;

const isEdit = computed(() => props.mode === 'edit');

const form = ref(emptyForm());
const wageAmountTouched = ref(false);

const alternateDrivers = computed(() =>
    props.drivers.filter((driver) => driver.id !== form.value.primary_driver_id),
);

const resolvedWageAmount = computed(() => {
    if (form.value.wage_amount !== '') {
        return Number(form.value.wage_amount);
    }

    const auto = resolveAutoWageAmount();

    return auto === '' ? 0 : Number(auto);
});

const isWagePayable = computed(() => resolvedWageAmount.value > 0);

const paymentStatusLabel = computed(() => {
    if (!isWagePayable.value) {
        return 'Not applicable';
    }

    const status = props.attendance?.wage_payment_status;

    if (isEdit.value && status) {
        return String(status).toLowerCase() === 'paid' ? 'Paid' : 'Unpaid';
    }

    return 'Unpaid';
});

function emptyForm() {
    return {
        attendance_date: new Date().toISOString().slice(0, 10),
        vehicle_id: '',
        primary_driver_id: '',
        temporary_driver_id: '',
        temporary_assignment_remarks: '',
        status: 'PRESENT',
        wage_amount: '',
        wage_transfer_notes: '',
    };
}

function fillForm(record) {
    wageAmountTouched.value = Boolean(record?.wage_amount);

    form.value = {
        attendance_date: record?.attendance_date ?? new Date().toISOString().slice(0, 10),
        vehicle_id: record?.vehicle_id ?? '',
        primary_driver_id: record?.primary_driver_id ?? '',
        temporary_driver_id: record?.temporary_driver_id ?? '',
        temporary_assignment_remarks: record?.temporary_assignment_remarks ?? '',
        status: record?.status ?? 'PRESENT',
        wage_amount: record?.wage_amount !== null && record?.wage_amount !== undefined ? String(record.wage_amount) : '',
        wage_transfer_notes: record?.wage_transfer_notes ?? '',
    };

    if (!wageAmountTouched.value) {
        applyAutoWageFromPrimaryDriver();
    }
}

function resolveAutoWageAmount() {
    const driver = props.drivers.find((item) => item.id === form.value.primary_driver_id);

    if (!driver) {
        return '';
    }

    const dailyWage = Number(driver.daily_wage ?? 0);

    if (form.value.status === 'PRESENT') {
        return String(dailyWage);
    }

    if (form.value.status === 'ABSENT' && form.value.temporary_driver_id) {
        return String(dailyWage);
    }

    return '0';
}

function applyAutoWageFromPrimaryDriver() {
    if (wageAmountTouched.value) {
        return;
    }

    form.value.wage_amount = resolveAutoWageAmount();
}

function onVehicleChange() {
    const vehicle = props.vehicles.find((item) => item.id === form.value.vehicle_id);

    if (vehicle?.primary_driver_id && !form.value.primary_driver_id) {
        form.value.primary_driver_id = vehicle.primary_driver_id;
        applyAutoWageFromPrimaryDriver();
    }
}

watch(
    () => props.show,
    (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false });
        }

        if (visible) {
            fillForm(props.attendance);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

watch(
    () => props.attendance,
    (record) => {
        if (props.show) {
            fillForm(record);
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    const payload = {
        attendance_date: form.value.attendance_date,
        vehicle_id: form.value.vehicle_id,
        primary_driver_id: form.value.primary_driver_id,
        status: form.value.status,
        marked_via: 'admin_panel',
        temporary_assignment_remarks: form.value.temporary_assignment_remarks || null,
        wage_transfer_notes: form.value.wage_transfer_notes || null,
        temporary_driver_id:
            form.value.status === 'ABSENT' && form.value.temporary_driver_id
                ? form.value.temporary_driver_id
                : null,
    };

    if (form.value.wage_amount !== '') {
        payload.wage_amount = Number(form.value.wage_amount);
    }

    emit('submit', payload);
}
</script>
