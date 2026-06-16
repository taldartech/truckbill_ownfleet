<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <div>
                        <h5 :id="`${modalId}Label`" class="modal-title mb-0">
                            <i class="bi bi-calendar-check"></i>
                            {{ isEdit ? 'Edit Attendance' : 'Mark Attendance' }}
                        </h5>
                        <p v-if="contextSummary" class="small mb-0 mt-1 text-white-50">{{ contextSummary }}</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError || contextError" class="alert alert-danger py-2">
                            {{ formError || contextError }}
                        </div>

                        <div class="mb-3">
                            <label :for="`${modalId}_exception`" class="form-label">Attendance Type *</label>
                            <select
                                :id="`${modalId}_exception`"
                                v-model="form.exception_type"
                                class="form-select"
                                :class="{ 'is-invalid': hasFieldError(fieldErrors, 'status') }"
                                required
                            >
                                <option v-if="isEdit" value="BLANK">· — Blank (clear mark)</option>
                                <option value="ABSENT">A — Absent</option>
                                <option value="ABSENT_SUBSTITUTE">S — Substitute</option>
                            </select>
                            <div v-if="fieldErrorMessage(fieldErrors, 'status')" class="invalid-feedback d-block">
                                {{ fieldErrorMessage(fieldErrors, 'status') }}
                            </div>
                        </div>

                        <div v-if="form.exception_type === 'ABSENT_SUBSTITUTE'" class="mb-3">
                            <label :for="`${modalId}_temp`" class="form-label">Substitute Driver *</label>
                            <SearchableSelect
                                :input-id="`${modalId}_temp`"
                                v-model="form.temporary_driver_id"
                                :items="substituteSelectItems"
                                placeholder="Select substitute"
                                search-placeholder="Search driver name…"
                                empty-message="No matching drivers."
                                :invalid="hasFieldError(fieldErrors, 'temporary_driver_id')"
                                :error-message="fieldErrorMessage(fieldErrors, 'temporary_driver_id')"
                            />
                        </div>

                        <div class="mb-0">
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
import SearchableSelect from '../ui/SearchableSelect.vue';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import {
    buildBusySubstituteDriversByDate,
    isSubstituteDriverBusy,
    substituteBusyLabel,
} from '../../utils/substituteDriverAvailability';
import { formatDate } from '../../utils/datetime';

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
const contextError = ref('');

const alternateDrivers = computed(() =>
    props.drivers.filter((driver) => driver.id !== form.value.primary_driver_id),
);

const busySubstituteDriversByDate = computed(() =>
    buildBusySubstituteDriversByDate({
        date: form.value.attendance_date,
        monthOverview: props.monthOverview,
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

const substituteSelectItems = computed(() => {
    const available = availableSubstituteDrivers.value.map((driver) => ({
        id: driver.id,
        name: driverLabel(driver),
    }));

    const busy = busySubstituteDrivers.value.map((entry) => ({
        id: entry.driver.id,
        name: driverLabel(entry.driver),
        hint: `Busy — ${entry.busyLabel}`,
        disabled: true,
    }));

    return [...available, ...busy];
});

function driverLabel(driver) {
    const name = driver?.name ?? '—';

    return driver?.is_active === false ? `${name} (Inactive)` : name;
}

const contextSummary = computed(() => {
    if (!form.value.vehicle_id && !form.value.attendance_date) {
        return '';
    }

    const vehicle = props.vehicles.find((item) => item.id === form.value.vehicle_id);
    const vehicleLabel = vehicle?.registration_number ?? '—';
    const driver =
        props.drivers.find((item) => item.id === form.value.primary_driver_id) ??
        vehicle?.primary_driver ??
        vehicle?.current_primary_assignment?.driver;
    const driverLabelText = driver ? driverLabel(driver) : '—';
    const dateLabel = formatDate(form.value.attendance_date);

    return `${vehicleLabel} · ${dateLabel} · ${driverLabelText}`;
});

function emptyForm() {
    return {
        attendance_date: new Date().toISOString().slice(0, 10),
        vehicle_id: '',
        primary_driver_id: '',
        temporary_driver_id: '',
        temporary_assignment_remarks: '',
        exception_type: 'ABSENT',
    };
}

function mapRecordToAttendanceType(record) {
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
        exception_type: record ? mapRecordToAttendanceType(record) : 'ABSENT',
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

function ensureContextFields() {
    contextError.value = '';

    if (!form.value.vehicle_id || !form.value.attendance_date) {
        contextError.value = 'Open this form from a truck on the Month View grid.';

        return false;
    }

    if (!form.value.primary_driver_id) {
        applyPrimaryDriverFromVehicle();
    }

    if (!form.value.primary_driver_id) {
        contextError.value = 'This truck has no primary driver — assign one in Fleet first.';

        return false;
    }

    return true;
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
            contextError.value = '';
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
    if (form.value.exception_type === 'ABSENT_SUBSTITUTE') {
        return {
            status: 'ABSENT',
            temporary_driver_id: form.value.temporary_driver_id,
        };
    }

    return { status: 'ABSENT', temporary_driver_id: null };
}

function submit() {
    if (!ensureContextFields()) {
        return;
    }

    if (isEdit.value && form.value.exception_type === 'BLANK') {
        emit('delete', {
            record: props.attendance,
            exception_type: 'BLANK',
        });

        return;
    }

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
        temporary_driver_id: statusPayload.temporary_driver_id,
        exception_type: form.value.exception_type,
    });
}
</script>

<style scoped>
.substitute-option-busy {
    color: #dc3545;
}
</style>
