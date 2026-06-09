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
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i class="bi bi-calendar-range"></i>
                        Bulk Mark Attendance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <p class="text-muted small">
                            Mark the same attendance for every day in the date range (max 31 days).
                        </p>
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <div class="mb-3">
                            <label :for="`${modalId}_vehicle`" class="form-label">Truck *</label>
                            <select
                                :id="`${modalId}_vehicle`"
                                v-model="form.vehicle_id"
                                class="form-select"
                                :class="{ 'is-invalid': fieldErrors.vehicle_id }"
                                required
                            >
                                <option value="">Select truck</option>
                                <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                                    {{ vehicle.registration_number }}
                                </option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_from`" class="form-label">From *</label>
                                <input
                                    :id="`${modalId}_from`"
                                    v-model="form.from_date"
                                    type="date"
                                    class="form-control"
                                    :max="today"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_to`" class="form-label">To *</label>
                                <input
                                    :id="`${modalId}_to`"
                                    v-model="form.to_date"
                                    type="date"
                                    class="form-control"
                                    :max="today"
                                    required
                                />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label :for="`${modalId}_type`" class="form-label">Attendance Type *</label>
                            <select :id="`${modalId}_type`" v-model="form.attendance_type" class="form-select" required>
                                <option value="ABSENT">A — Absent</option>
                                <option value="ABSENT_SUBSTITUTE">S — Substitute</option>
                            </select>
                        </div>

                        <div v-if="form.attendance_type === 'ABSENT_SUBSTITUTE'" class="mb-3">
                            <label :for="`${modalId}_sub`" class="form-label">Substitute Driver *</label>
                            <select
                                :id="`${modalId}_sub`"
                                v-model="form.temporary_driver_id"
                                class="form-select"
                                required
                            >
                                <option value="">Select substitute</option>
                                <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                    {{ driver.name }}
                                </option>
                            </select>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Saving…' : 'Save Bulk Attendance' }}
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

const modalElement = ref(null);
let bootstrapModal = null;

const today = computed(() => new Date().toISOString().slice(0, 10));

const form = ref({
    vehicle_id: '',
    from_date: today.value,
    to_date: today.value,
    attendance_type: 'ABSENT',
    temporary_driver_id: '',
    temporary_assignment_remarks: '',
});

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
            form.value.vehicle_id = props.defaultVehicleId || '';
            form.value.from_date = props.defaultFromDate || today.value;
            form.value.to_date = props.defaultFromDate || today.value;
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
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
