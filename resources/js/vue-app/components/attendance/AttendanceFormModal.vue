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
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ isEdit ? 'Edit Attendance' : 'Mark Attendance' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <p class="text-muted small">
                            Normal working days are automatic from driver assignment. Only mark absences,
                            substitutes, or days the truck did not run.
                        </p>

                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_date`" class="form-label">Date *</label>
                                <input
                                    :id="`${modalId}_date`"
                                    v-model="form.attendance_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'attendance_date') }"
                                    required
                                />
                                <div
                                    v-if="fieldErrorMessage(fieldErrors, 'attendance_date')"
                                    class="invalid-feedback d-block"
                                >
                                    {{ fieldErrorMessage(fieldErrors, 'attendance_date') }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_vehicle`" class="form-label">Truck *</label>
                                <select
                                    :id="`${modalId}_vehicle`"
                                    v-model="form.vehicle_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'vehicle_id') }"
                                    required
                                    @change="onVehicleChange"
                                >
                                    <option value="">Select truck</option>
                                    <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                                        {{ vehicle.registration_number }}
                                    </option>
                                </select>
                                <div
                                    v-if="fieldErrorMessage(fieldErrors, 'vehicle_id')"
                                    class="invalid-feedback d-block"
                                >
                                    {{ fieldErrorMessage(fieldErrors, 'vehicle_id') }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_primary`" class="form-label">Primary Driver *</label>
                                <select
                                    :id="`${modalId}_primary`"
                                    v-model="form.primary_driver_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'primary_driver_id') }"
                                    required
                                >
                                    <option value="">Select driver</option>
                                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                        {{ driverLabel(driver) }}
                                    </option>
                                </select>
                                <div
                                    v-if="fieldErrorMessage(fieldErrors, 'primary_driver_id')"
                                    class="invalid-feedback d-block"
                                >
                                    {{ fieldErrorMessage(fieldErrors, 'primary_driver_id') }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_exception`" class="form-label">Exception Type *</label>
                                <select
                                    :id="`${modalId}_exception`"
                                    v-model="form.exception_type"
                                    class="form-select"
                                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'status') }"
                                    required
                                >
                                    <option value="ABSENT">Absent (no work)</option>
                                    <option value="ABSENT_SUBSTITUTE">Absent + substitute</option>
                                    <option value="NO_WORK_DAY">No working day (truck idle)</option>
                                </select>
                                <div v-if="fieldErrorMessage(fieldErrors, 'status')" class="invalid-feedback d-block">
                                    {{ fieldErrorMessage(fieldErrors, 'status') }}
                                </div>
                            </div>
                        </div>

                        <div v-if="form.exception_type === 'ABSENT_SUBSTITUTE'" class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_temp`" class="form-label">Substitute Driver *</label>
                                <select
                                    :id="`${modalId}_temp`"
                                    v-model="form.temporary_driver_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'temporary_driver_id') }"
                                    required
                                >
                                    <option value="">Select substitute</option>
                                    <optgroup v-if="availableSubstituteDrivers.length" label="Available">
                                        <option
                                            v-for="driver in availableSubstituteDrivers"
                                            :key="driver.id"
                                            :value="driver.id"
                                        >
                                            {{ driverLabel(driver) }}
                                        </option>
                                    </optgroup>
                                    <optgroup
                                        v-if="busySubstituteDrivers.length"
                                        label="Already assigned on this date"
                                    >
                                        <option
                                            v-for="entry in busySubstituteDrivers"
                                            :key="entry.driver.id"
                                            :value="entry.driver.id"
                                            disabled
                                            class="substitute-option-busy"
                                        >
                                            {{ driverLabel(entry.driver) }} — {{ entry.busyLabel }}
                                        </option>
                                    </optgroup>
                                </select>
                                <p class="form-text text-muted mb-0">
                                    Only drivers not assigned to any truck on this date can substitute. Names in red
                                    are already on another truck.
                                </p>
                                <div
                                    v-if="fieldErrorMessage(fieldErrors, 'temporary_driver_id')"
                                    class="invalid-feedback d-block"
                                >
                                    {{ fieldErrorMessage(fieldErrors, 'temporary_driver_id') }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_remarks`" class="form-label">Remarks</label>
                                <input
                                    :id="`${modalId}_remarks`"
                                    v-model="form.temporary_assignment_remarks"
                                    type="text"
                                    class="form-control"
                                    :class="{
                                        'is-invalid': hasFieldError(fieldErrors, 'temporary_assignment_remarks'),
                                    }"
                                    placeholder="Reason for substitute"
                                />
                                <div
                                    v-if="fieldErrorMessage(fieldErrors, 'temporary_assignment_remarks')"
                                    class="invalid-feedback d-block"
                                >
                                    {{ fieldErrorMessage(fieldErrors, 'temporary_assignment_remarks') }}
                                </div>
                            </div>
                        </div>

                        <div v-if="form.exception_type === 'ABSENT'" class="row mb-3">
                            <div class="col-md-12">
                                <label :for="`${modalId}_remarks`" class="form-label">Remarks</label>
                                <input
                                    :id="`${modalId}_remarks`"
                                    v-model="form.temporary_assignment_remarks"
                                    type="text"
                                    class="form-control"
                                    :class="{
                                        'is-invalid': hasFieldError(fieldErrors, 'temporary_assignment_remarks'),
                                    }"
                                    placeholder="Optional note"
                                />
                                <div
                                    v-if="fieldErrorMessage(fieldErrors, 'temporary_assignment_remarks')"
                                    class="invalid-feedback d-block"
                                >
                                    {{ fieldErrorMessage(fieldErrors, 'temporary_assignment_remarks') }}
                                </div>
                            </div>
                        </div>

                        <div v-if="isWagePayable" class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Monthly Payment Base</label>
                                <input type="text" class="form-control" :value="monthlyBaseLabel" readonly />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Day Accrual (substitute)</label>
                                <input type="text" class="form-control" :value="dayAccrualLabel" readonly />
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label :for="`${modalId}_notes`" class="form-label">Notes</label>
                                <input
                                    :id="`${modalId}_notes`"
                                    v-model="form.wage_transfer_notes"
                                    type="text"
                                    class="form-control"
                                    placeholder="Optional internal note"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            v-if="isEdit"
                            type="button"
                            class="btn btn-outline-danger me-auto"
                            :disabled="saving"
                            @click="removeException"
                        >
                            Remove exception
                        </button>
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Saving...' : isEdit ? 'Update' : 'Save Exception' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { monthlyDayAccrual } from '../../utils/monthlyWage';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import {
    buildBusySubstituteDriversByDate,
    isSubstituteDriverBusy,
    substituteBusyLabel,
} from '../../utils/substituteDriverAvailability';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    attendance: { type: Object, default: null },
    vehicles: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    monthOverview: { type: Object, default: () => ({ rows: [] }) },
    saving: { type: Boolean, default: false },
    modalId: { type: String, required: true },
    prefilledDate: { type: String, default: '' },
    prefilledVehicleId: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit', 'delete']);

const modalElement = ref(null);
let bootstrapModal = null;

const isEdit = computed(() => props.mode === 'edit');

const form = ref(emptyForm());

const alternateDrivers = computed(() =>
    props.drivers.filter((driver) => driver.id !== form.value.primary_driver_id),
);

const busySubstituteDriversByDate = computed(() =>
    buildBusySubstituteDriversByDate({
        date: form.value.attendance_date,
        monthOverview: props.monthOverview,
        vehicles: props.vehicles,
        excludeAttendanceId: props.attendance?.id ?? null,
    }),
);

const activeAlternateDrivers = computed(() =>
    alternateDrivers.value.filter((driver) => driver.is_active !== false),
);

const availableSubstituteDrivers = computed(() => {
    const available = activeAlternateDrivers.value.filter(
        (driver) => !isSubstituteDriverBusy(busySubstituteDriversByDate.value, driver.id),
    );

    if (!form.value.temporary_driver_id) {
        return available;
    }

    const selected = props.drivers.find((driver) => driver.id === form.value.temporary_driver_id);

    if (
        selected &&
        !available.some((driver) => driver.id === selected.id) &&
        !isSubstituteDriverBusy(busySubstituteDriversByDate.value, selected.id)
    ) {
        return [selected, ...available];
    }

    return available;
});

const busySubstituteDrivers = computed(() =>
    activeAlternateDrivers.value
        .filter((driver) => isSubstituteDriverBusy(busySubstituteDriversByDate.value, driver.id))
        .map((driver) => ({
            driver,
            busyLabel: substituteBusyLabel(busySubstituteDriversByDate.value.get(String(driver.id))),
        }))
        .sort((left, right) => left.driver.name.localeCompare(right.driver.name)),
);

function driverLabel(driver) {
    const name = driver?.name ?? '—';

    return driver?.is_active === false ? `${name} (Inactive)` : name;
}

const substituteDriver = computed(() => {
    if (form.value.exception_type !== 'ABSENT_SUBSTITUTE') {
        return null;
    }

    return props.drivers.find((item) => item.id === form.value.temporary_driver_id) ?? null;
});

const monthlyBaseAmount = computed(() => Number(substituteDriver.value?.daily_wage ?? 0));

const dayAccrualAmount = computed(() =>
    monthlyDayAccrual(monthlyBaseAmount.value, form.value.attendance_date),
);

const isWagePayable = computed(
    () => form.value.exception_type === 'ABSENT_SUBSTITUTE' && dayAccrualAmount.value > 0,
);

const monthlyBaseLabel = computed(() => {
    if (!substituteDriver.value) {
        return '—';
    }

    return monthlyBaseAmount.value > 0 ? `₹ ${monthlyBaseAmount.value.toFixed(2)}` : 'Not set on driver';
});

const dayAccrualLabel = computed(() =>
    isWagePayable.value ? `₹ ${dayAccrualAmount.value.toFixed(2)}` : '—',
);

function emptyForm() {
    return {
        attendance_date: new Date().toISOString().slice(0, 10),
        vehicle_id: '',
        primary_driver_id: '',
        temporary_driver_id: '',
        temporary_assignment_remarks: '',
        exception_type: 'ABSENT',
        wage_transfer_notes: '',
    };
}

function mapRecordToExceptionType(record) {
    if (record?.status === 'NO_WORK_DAY') {
        return 'NO_WORK_DAY';
    }

    if (record?.status === 'ABSENT' && record?.temporary_driver_id) {
        return 'ABSENT_SUBSTITUTE';
    }

    return 'ABSENT';
}

function fillForm(record) {
    form.value = {
        attendance_date:
            record?.attendance_date ??
            props.prefilledDate ??
            new Date().toISOString().slice(0, 10),
        vehicle_id: record?.vehicle_id ?? props.prefilledVehicleId ?? '',
        primary_driver_id: record?.primary_driver_id ?? '',
        temporary_driver_id: record?.temporary_driver_id ?? '',
        temporary_assignment_remarks: record?.temporary_assignment_remarks ?? '',
        exception_type: record ? mapRecordToExceptionType(record) : 'ABSENT',
        wage_transfer_notes: record?.wage_transfer_notes ?? '',
    };

    if (!isEdit.value && form.value.vehicle_id) {
        applyPrimaryDriverFromVehicle();
    }
}

function resolveVehiclePrimaryDriverId(vehicle) {
    if (!vehicle) {
        return '';
    }

    return vehicle.primary_driver_id ?? vehicle.current_primary_assignment?.driver_id ?? '';
}

function applyPrimaryDriverFromVehicle() {
    const vehicle = props.vehicles.find((item) => item.id === form.value.vehicle_id);
    const driverId = resolveVehiclePrimaryDriverId(vehicle);

    if (driverId) {
        form.value.primary_driver_id = driverId;

        if (form.value.temporary_driver_id === driverId) {
            form.value.temporary_driver_id = '';
        }
    }
}

function onVehicleChange() {
    applyPrimaryDriverFromVehicle();
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

watch(
    () => [form.value.attendance_date, busySubstituteDriversByDate.value],
    () => {
        if (
            form.value.temporary_driver_id &&
            isSubstituteDriverBusy(busySubstituteDriversByDate.value, form.value.temporary_driver_id)
        ) {
            form.value.temporary_driver_id = '';
        }
    },
);

function close() {
    emit('close');
}

function buildStatusPayload() {
    if (form.value.exception_type === 'NO_WORK_DAY') {
        return { status: 'NO_WORK_DAY', temporary_driver_id: null };
    }

    if (form.value.exception_type === 'ABSENT_SUBSTITUTE') {
        return {
            status: 'ABSENT',
            temporary_driver_id: form.value.temporary_driver_id,
        };
    }

    return { status: 'ABSENT', temporary_driver_id: null };
}

function submit() {
    const statusPayload = buildStatusPayload();

    if (
        form.value.exception_type === 'ABSENT_SUBSTITUTE' &&
        form.value.temporary_driver_id &&
        isSubstituteDriverBusy(busySubstituteDriversByDate.value, form.value.temporary_driver_id)
    ) {
        return;
    }

    emit('submit', {
        attendance_date: form.value.attendance_date,
        vehicle_id: form.value.vehicle_id,
        primary_driver_id: form.value.primary_driver_id,
        status: statusPayload.status,
        marked_via: 'admin_panel',
        temporary_assignment_remarks: form.value.temporary_assignment_remarks || null,
        wage_transfer_notes: form.value.wage_transfer_notes || null,
        temporary_driver_id: statusPayload.temporary_driver_id,
    });
}

function removeException() {
    emit('delete', props.attendance);
}
</script>

<style scoped>
.substitute-option-busy {
    color: #dc3545;
}
</style>
