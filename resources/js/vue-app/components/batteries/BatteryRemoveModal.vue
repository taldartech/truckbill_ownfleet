<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        size="lg"
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="fas fa-plug-circle-minus text-warning me-2"></i>
            Remove Battery from Truck
        </template>

        <div class="row g-2 mb-3">
            <div class="col-sm-6">
                <div class="battery-modal-summary h-100">
                    <div class="battery-modal-summary__label">Battery</div>
                    <div class="fw-bold text-dark">
                        <i class="fas fa-car-battery text-warning me-1"></i>
                        {{ batterySerial }}
                    </div>
                    <div class="text-muted small">
                        Fitted {{ installation?.installed_at ? formatDate(installation.installed_at) : '—' }}
                        at {{ formatKm(kmAtInstall) }}
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="battery-modal-summary h-100">
                    <div class="battery-modal-summary__label">Truck</div>
                    <div class="fw-bold text-dark">
                        <i class="fas fa-truck text-secondary me-1"></i>
                        {{ vehicleRegistration || '—' }}
                    </div>
                    <div class="text-muted small">
                        Slot {{ installation?.slot_name || '—' }} · Odometer {{ formatKm(vehicleOdometer) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border small py-2 mb-3">
            <i class="fas fa-circle-info text-primary me-1"></i>
            The battery moves to <strong>Removed / Standby</strong> and can be fitted to another truck later.
            The slot on this truck becomes free.
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold" for="batteryRemove_reason">
                    Reason <span class="text-danger">*</span>
                </label>
                <select
                    id="batteryRemove_reason"
                    v-model="form.removal_reason"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.removal_reason }"
                    required
                >
                    <option v-for="opt in BATTERY_REMOVAL_REASON_OPTIONS" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <div v-if="fieldErrors.removal_reason" class="invalid-feedback d-block">
                    {{ fieldErrors.removal_reason[0] }}
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold" for="batteryRemove_date">
                    Removal Date <span class="text-danger">*</span>
                </label>
                <input
                    id="batteryRemove_date"
                    v-model="form.removed_at"
                    type="date"
                    class="form-control"
                    :min="installation?.installed_at || undefined"
                    :max="todayDate"
                    :class="{ 'is-invalid': fieldErrors.removed_at }"
                    required
                />
                <div v-if="fieldErrors.removed_at" class="invalid-feedback d-block">
                    {{ fieldErrors.removed_at[0] }}
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold" for="batteryRemove_odometer">
                    Odometer <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input
                        id="batteryRemove_odometer"
                        v-model.number="form.odometer_km"
                        type="number"
                        :min="kmAtInstall"
                        step="1"
                        class="form-control"
                        :class="{ 'is-invalid': fieldErrors.odometer_km || odometerTooLow }"
                        required
                    />
                    <span class="input-group-text">KM</span>
                </div>
            </div>
            <div v-if="fieldErrors.odometer_km || odometerTooLow" class="col-12 mt-1">
                <div class="invalid-feedback d-block text-end">
                    {{ fieldErrors.odometer_km?.[0] || `Must be at least ${formatKm(kmAtInstall)} (reading at installation).` }}
                </div>
            </div>

            <div class="col-12">
                <div class="small text-muted">
                    <i class="fas fa-road me-1"></i>
                    Stint on this truck: <strong class="text-dark">{{ formatKm(stintPreviewKm) }}</strong>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label" for="batteryRemove_notes">Notes</label>
                <textarea
                    id="batteryRemove_notes"
                    v-model="form.notes"
                    rows="2"
                    class="form-control"
                    maxlength="2000"
                    placeholder="Why it was removed, where it is stored…"
                ></textarea>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="btn btn-outline-secondary px-4"
                :disabled="saving"
                @click="close"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="btn btn-warning px-4 d-flex align-items-center gap-2"
                :disabled="saving || !installation?.id || odometerTooLow"
            >
                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                <span>Remove Battery</span>
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import { removeBatteryInstallation } from '../../api/batteries';
import { formatDate, toDateInputValue } from '../../utils/datetime';
import { BATTERY_REMOVAL_REASON_OPTIONS, formatKm } from '../../constants/battery';
import './battery.css';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    installation: {
        type: Object,
        default: null,
    },
    batterySerial: {
        type: String,
        default: '—',
    },
    vehicle: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'removed']);

const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});

const todayDate = computed(() => toDateInputValue());
const kmAtInstall = computed(() => Number(props.installation?.km_at_installation ?? 0));
const vehicleRegistration = computed(() => props.vehicle?.registration_number ?? props.installation?.vehicle_registration ?? '');
const vehicleOdometer = computed(() => Number(props.vehicle?.odometer_km ?? props.installation?.vehicle_odometer_km ?? 0));

const form = ref(defaultForm());

function defaultForm() {
    return {
        removal_reason: 'VEHICLE_MAINTENANCE',
        removed_at: toDateInputValue(),
        odometer_km: 0,
        notes: '',
    };
}

const odometerTooLow = computed(() =>
    form.value.odometer_km !== '' && Number(form.value.odometer_km) < kmAtInstall.value,
);

const stintPreviewKm = computed(() => Math.max(0, Number(form.value.odometer_km || 0) - kmAtInstall.value));

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};
        form.value = {
            ...defaultForm(),
            odometer_km: Math.max(vehicleOdometer.value, kmAtInstall.value),
        };
    },
);

function close() {
    if (!saving.value) {
        emit('close');
    }
}

async function submit() {
    if (!props.installation?.id || odometerTooLow.value) {
        return;
    }

    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const result = await removeBatteryInstallation(props.installation.id, {
            removal_reason: form.value.removal_reason,
            removed_at: form.value.removed_at,
            odometer_km: form.value.odometer_km,
            notes: form.value.notes || null,
        });
        emit('removed', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.errors.installation?.[0] || err.response.data.message || 'Please fix the highlighted errors.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to remove battery.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
