<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="bi bi-calendar-range"></i>
            Bulk Mark Attendance
        </template>

        <p class="text-muted small">
            Mark the same attendance for every day in the date range (max 31 days).
        </p>

        <div class="mb-3">
            <label :for="`${modalId}_vehicle`" class="form-label">Truck *</label>
            <VehicleSelect
                :input-id="`${modalId}_vehicle`"
                v-model="form.vehicle_id"
                :vehicles="props.vehicles"
                placeholder="Select truck"
                :invalid="Boolean(fieldErrors.vehicle_id)"
                :error-message="fieldErrors.vehicle_id"
            />
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label :for="`${modalId}_from`" class="form-label">From *</label>
                <AppDateInput
                    :input-id="`${modalId}_from`"
                    v-model="form.from_date"
                    :max="today"
                    required
                />
            </div>
            <div class="col-md-6">
                <label :for="`${modalId}_to`" class="form-label">To *</label>
                <AppDateInput
                    :input-id="`${modalId}_to`"
                    v-model="form.to_date"
                    :max="today"
                    required
                />
            </div>
        </div>

        <div class="mb-3">
            <label :for="`${modalId}_type`" class="form-label">Attendance Type *</label>
            <AppSelect
                :input-id="`${modalId}_type`"
                v-model="form.attendance_type"
                :options="attendanceTypeOptions"
                placeholder="Select type"
                search-placeholder="Search type…"
            />
        </div>

        <div v-if="form.attendance_type === 'ABSENT_SUBSTITUTE'" class="mb-3">
            <label :for="`${modalId}_sub`" class="form-label">Substitute Driver *</label>
            <AppSelect
                :input-id="`${modalId}_sub`"
                v-model="form.temporary_driver_id"
                :options="substituteSelectItems"
                placeholder="Select substitute"
                search-placeholder="Search driver…"
            />
        </div>

        <div class="mb-3">
            <label :for="`${modalId}_remarks`" class="form-label">Remarks</label>
            <input
                :id="`${modalId}_remarks`"
                v-model="form.temporary_assignment_remarks"
                type="text"
                class="form-control"
                placeholder="Optional note"
            />
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Saving…' : 'Save Bulk Attendance' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';
import VehicleSelect from '../ui/VehicleSelect.vue';
import { toDateInputValue } from '../../utils/datetime';

const props = defineProps({
    show: { type: Boolean, default: false },
    vehicles: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    modalId: { type: String, required: true },
    defaultVehicleId: { type: String, default: '' },
    defaultFromDate: { type: String, default: '' },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const today = computed(() => toDateInputValue());

const attendanceTypeOptions = [
    { value: 'ABSENT', label: 'A — Absent' },
    { value: 'ABSENT_SUBSTITUTE', label: 'S — Substitute' },
];

const form = ref({
    vehicle_id: '',
    from_date: today.value,
    to_date: today.value,
    attendance_type: 'ABSENT',
    temporary_driver_id: '',
    temporary_assignment_remarks: '',
});

const selectedVehicle = computed(() =>
    props.vehicles.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null,
);

const selectedPrimaryDriverId = computed(() => resolveVehiclePrimaryDriverId(selectedVehicle.value));

const substituteSelectItems = computed(() =>
    props.drivers
        .filter(
            (driver) =>
                driver.is_active !== false && String(driver.id) !== String(selectedPrimaryDriverId.value ?? ''),
        )
        .sort((left, right) => left.name.localeCompare(right.name))
        .map((driver) => ({
            id: driver.id,
            name: driverLabel(driver),
        })),
);

function resolveVehiclePrimaryDriverId(vehicle) {
    if (!vehicle) {
        return '';
    }

    return (
        vehicle.primary_driver_id ??
        vehicle.current_primary_assignment?.driver_id ??
        vehicle.current_primary_assignment?.driver?.id ??
        ''
    );
}

function driverLabel(driver) {
    const name = driver?.name ?? '—';

    return driver?.is_active === false ? `${name} (Inactive)` : name;
}

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            form.value.vehicle_id = props.defaultVehicleId || '';
            form.value.from_date = props.defaultFromDate || today.value;
            form.value.to_date = props.defaultFromDate || today.value;
        }
    },
);

watch(
    () => form.value.vehicle_id,
    () => {
        if (
            form.value.temporary_driver_id &&
            String(form.value.temporary_driver_id) === String(selectedPrimaryDriverId.value)
        ) {
            form.value.temporary_driver_id = '';
        }
    },
);

watch(
    () => form.value.attendance_type,
    (type) => {
        if (type !== 'ABSENT_SUBSTITUTE') {
            form.value.temporary_driver_id = '';
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    emit('submit', {
        vehicle_id: form.value.vehicle_id,
        from_date: form.value.from_date,
        to_date: form.value.to_date,
        attendance_type: form.value.attendance_type,
        temporary_driver_id:
            form.value.attendance_type === 'ABSENT_SUBSTITUTE' ? form.value.temporary_driver_id : null,
        temporary_assignment_remarks: form.value.temporary_assignment_remarks || null,
    });
}
</script>
