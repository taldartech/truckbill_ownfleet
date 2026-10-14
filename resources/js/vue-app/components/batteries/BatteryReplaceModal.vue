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
            <i class="fas fa-arrows-rotate text-primary me-2"></i>
            Replace Battery
        </template>

        <div class="row g-2 mb-3">
            <div class="col-sm-6">
                <div class="battery-modal-summary h-100">
                    <div class="battery-modal-summary__label">Truck</div>
                    <div class="fw-bold text-dark">
                        <i class="fas fa-truck text-secondary me-1"></i>
                        {{ vehicleRegistration || '—' }}
                    </div>
                    <div class="text-muted small">
                        Odometer: {{ formatKm(vehicleOdometer) }}
                        <span v-if="installation?.slot_name"> · Slot {{ installation.slot_name }}</span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="battery-modal-summary h-100">
                    <div class="battery-modal-summary__label">Battery being removed</div>
                    <div class="fw-bold text-danger">
                        <i class="fas fa-car-battery me-1"></i>
                        {{ oldBatterySerial }}
                    </div>
                    <div class="text-muted small">
                        Fitted {{ installation?.installed_at ? formatDate(installation.installed_at) : '—' }}
                        at {{ formatKm(kmAtInstall) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold" for="batteryReplace_new">
                    New Battery <span class="text-danger">*</span>
                </label>
                <AppSelect
                    v-model="form.new_battery_id"
                    input-id="batteryReplace_new"
                    :options="availableBatteryOptions"
                    placeholder="Select battery from stock"
                    search-placeholder="Search serial no / brand…"
                    empty-message="No battery available in stock."
                    :invalid="Boolean(fieldErrors.new_battery_id)"
                    :error-message="fieldErrors.new_battery_id?.[0] ?? ''"
                    :clearable="false"
                />
                <div v-if="!loadingStock && availableBatteries.length === 0" class="form-text text-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No batteries in stock. Add one to inventory, or use Remove instead.
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="batteryReplace_reason">
                    Reason <span class="text-danger">*</span>
                </label>
                <select
                    id="batteryReplace_reason"
                    v-model="form.reason"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.reason }"
                    required
                >
                    <option v-for="opt in BATTERY_REMOVAL_REASON_OPTIONS" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <div v-if="fieldErrors.reason" class="invalid-feedback d-block">
                    {{ fieldErrors.reason[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold" for="batteryReplace_date">
                    Replacement Date <span class="text-danger">*</span>
                </label>
                <input
                    id="batteryReplace_date"
                    v-model="form.replaced_at"
                    type="date"
                    class="form-control"
                    :min="installation?.installed_at || undefined"
                    :max="todayDate"
                    :class="{ 'is-invalid': fieldErrors.replaced_at }"
                    required
                />
                <div v-if="fieldErrors.replaced_at" class="invalid-feedback d-block">
                    {{ fieldErrors.replaced_at[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold" for="batteryReplace_odometer">
                    Current Odometer <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input
                        id="batteryReplace_odometer"
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
                <div v-if="fieldErrors.odometer_km" class="invalid-feedback d-block">
                    {{ fieldErrors.odometer_km[0] }}
                </div>
                <div v-else-if="odometerTooLow" class="invalid-feedback d-block">
                    Must be at least {{ formatKm(kmAtInstall) }} (reading at installation).
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold" for="batteryReplace_slot">Slot for New Battery</label>
                <input
                    id="batteryReplace_slot"
                    v-model="form.slot_name"
                    type="text"
                    class="form-control"
                    maxlength="50"
                    :class="{ 'is-invalid': fieldErrors.slot_name }"
                />
            </div>

            <div class="col-12">
                <div class="d-flex align-items-center gap-2 small text-muted">
                    <i class="fas fa-road"></i>
                    Old battery will close its stint at
                    <strong class="text-dark">{{ formatKm(stintPreviewKm) }}</strong>
                    on this truck.
                </div>
            </div>

            <div class="col-12">
                <label class="form-label" for="batteryReplace_notes">Notes</label>
                <textarea
                    id="batteryReplace_notes"
                    v-model="form.notes"
                    rows="2"
                    class="form-control"
                    maxlength="2000"
                    placeholder="Technician notes, warranty claim number, failure diagnosis…"
                    :class="{ 'is-invalid': fieldErrors.notes }"
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
                class="btn btn-primary px-4 d-flex align-items-center gap-2"
                :disabled="saving || !canSubmit"
            >
                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                <span>Confirm Replacement</span>
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';
import { getInStockBatteries, replaceBattery } from '../../api/batteries';
import { formatDate, toDateInputValue } from '../../utils/datetime';
import { BATTERY_REMOVAL_REASON_OPTIONS, availableBatteryLabel, formatKm } from '../../constants/battery';
import './battery.css';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    battery: {
        type: Object,
        default: null,
    },
    vehicle: {
        type: Object,
        default: null,
    },
    currentInstallation: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'replaced']);

const saving = ref(false);
const loadingStock = ref(false);
const formError = ref('');
const fieldErrors = ref({});
const availableBatteries = ref([]);

const todayDate = computed(() => toDateInputValue());

const installation = computed(() => props.currentInstallation ?? props.battery?.active_installation ?? null);
const oldBatteryId = computed(() => props.battery?.id ?? installation.value?.battery_id ?? installation.value?.battery?.id ?? '');
const oldBatterySerial = computed(() => props.battery?.serial_number ?? installation.value?.battery?.serial_number ?? '—');
const vehicleId = computed(() => props.vehicle?.id ?? installation.value?.vehicle_id ?? '');
const vehicleRegistration = computed(() => props.vehicle?.registration_number ?? installation.value?.vehicle_registration ?? '');
const vehicleOdometer = computed(() => Number(props.vehicle?.odometer_km ?? installation.value?.vehicle_odometer_km ?? 0));
const kmAtInstall = computed(() => Number(installation.value?.km_at_installation ?? 0));

const availableBatteryOptions = computed(() =>
    availableBatteries.value
        .filter((b) => b.id !== oldBatteryId.value)
        .map((b) => ({ value: b.id, label: availableBatteryLabel(b) })),
);

const form = ref(defaultForm());

function defaultForm() {
    return {
        new_battery_id: '',
        slot_name: 'Main #1',
        replaced_at: toDateInputValue(),
        odometer_km: 0,
        reason: 'BATTERY_FAILED',
        notes: '',
    };
}

const odometerTooLow = computed(() =>
    form.value.odometer_km !== '' && Number(form.value.odometer_km) < kmAtInstall.value,
);

const stintPreviewKm = computed(() => Math.max(0, Number(form.value.odometer_km || 0) - kmAtInstall.value));

const canSubmit = computed(() =>
    Boolean(vehicleId.value && oldBatteryId.value && form.value.new_battery_id) && !odometerTooLow.value,
);

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};
        form.value = {
            ...defaultForm(),
            slot_name: installation.value?.slot_name || 'Main #1',
            odometer_km: Math.max(vehicleOdometer.value, kmAtInstall.value),
        };

        loadingStock.value = true;
        try {
            availableBatteries.value = await getInStockBatteries();
        } catch {
            availableBatteries.value = [];
        } finally {
            loadingStock.value = false;
        }
    },
);

function close() {
    if (!saving.value) {
        emit('close');
    }
}

async function submit() {
    if (!canSubmit.value) {
        return;
    }

    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const result = await replaceBattery({
            vehicle_id: vehicleId.value,
            old_battery_id: oldBatteryId.value,
            new_battery_id: form.value.new_battery_id,
            slot_name: String(form.value.slot_name || '').trim() || null,
            replaced_at: form.value.replaced_at,
            odometer_km: form.value.odometer_km,
            reason: form.value.reason,
            notes: form.value.notes || null,
        });
        emit('replaced', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.errors.old_battery_id?.[0] || err.response.data.message || 'Please fix the highlighted errors.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to replace battery.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
