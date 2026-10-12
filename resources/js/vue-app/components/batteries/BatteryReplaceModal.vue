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

        <!-- Vehicle & Current Battery Header Badge -->
        <div class="p-3 mb-3 rounded-3 bg-light border">
            <div class="row g-2 align-items-center">
                <div class="col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold">Truck</div>
                    <div class="fs-6 fw-bold text-dark d-flex align-items-center gap-2 mt-1">
                        <i class="fas fa-truck text-secondary"></i>
                        {{ vehicle?.registration_number || '—' }}
                        <span v-if="vehicle?.odometer_km" class="badge bg-secondary">
                            {{ Number(vehicle.odometer_km).toLocaleString() }} KM
                        </span>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small text-uppercase fw-semibold">Current Battery (To Remove)</div>
                    <div class="fs-6 fw-bold text-danger d-flex align-items-center gap-2 mt-1">
                        <i class="fas fa-car-battery"></i>
                        {{ oldBatterySerial }}
                        <span v-if="currentInstallation?.slot_name" class="badge bg-light text-dark border">
                            {{ currentInstallation.slot_name }}
                        </span>
                    </div>
                    <div class="text-muted small">
                        {{ oldBatterySpecs }}
                        <span v-if="currentStintKm !== null"> • Stint: {{ Number(currentStintKm).toLocaleString() }} KM</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- New Battery Selection -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    New Battery (From Stock) <span class="text-danger">*</span>
                </label>
                <select
                    v-model="form.new_battery_id"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.new_battery_id }"
                    required
                >
                    <option value="" disabled>-- Select In-Stock Battery --</option>
                    <option
                        v-for="b in inStockBatteries"
                        :key="b.id"
                        :value="b.id"
                    >
                        {{ b.serial_number }} ({{ b.brand }} - {{ b.capacity_ah }} Ah, {{ b.voltage }})
                    </option>
                </select>
                <div v-if="fieldErrors.new_battery_id" class="invalid-feedback d-block">
                    {{ fieldErrors.new_battery_id[0] }}
                </div>
                <div v-if="inStockBatteries.length === 0 && !loadingStock" class="form-text text-warning small">
                    <i class="fas fa-exclamation-triangle me-1"></i> No in-stock batteries available. Add a battery to inventory first.
                </div>
            </div>

            <!-- Slot Name -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Battery Slot / Position</label>
                <input
                    v-model="form.slot_name"
                    type="text"
                    class="form-control"
                    placeholder="e.g. Main #1, Slot 1"
                    :class="{ 'is-invalid': fieldErrors.slot_name }"
                />
            </div>

            <!-- Odometer Reading -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Current Odometer (KM) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input
                        v-model.number="form.odometer_km"
                        type="number"
                        min="0"
                        step="1"
                        class="form-control"
                        placeholder="182450"
                        :class="{ 'is-invalid': fieldErrors.odometer_km }"
                        required
                    />
                    <span class="input-group-text">KM</span>
                </div>
                <div v-if="fieldErrors.odometer_km" class="invalid-feedback d-block">
                    {{ fieldErrors.odometer_km[0] }}
                </div>
            </div>

            <!-- Replacement Date -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Replacement Date <span class="text-danger">*</span>
                </label>
                <input
                    v-model="form.replaced_at"
                    type="date"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.replaced_at }"
                    required
                />
                <div v-if="fieldErrors.replaced_at" class="invalid-feedback d-block">
                    {{ fieldErrors.replaced_at[0] }}
                </div>
            </div>

            <!-- Replacement Reason -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Reason for Replacement <span class="text-danger">*</span>
                </label>
                <select
                    v-model="form.reason"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.reason }"
                    required
                >
                    <option value="BATTERY_FAILED">Battery Failed (Dead / Non-rechargeable)</option>
                    <option value="LOW_VOLTAGE">Low Voltage / Weak Cranking</option>
                    <option value="SCHEDULED_AGING">Scheduled Aging / Preventive Replacement</option>
                    <option value="PHYSICAL_DAMAGE">Physical Damage / Acid Leakage</option>
                    <option value="WARRANTY_CLAIM">Warranty Claim Replacement</option>
                    <option value="TRANSFERRED">Transferred to Another Vehicle</option>
                    <option value="VEHICLE_MAINTENANCE">Vehicle Electrical Maintenance</option>
                </select>
                <div v-if="fieldErrors.reason" class="invalid-feedback d-block">
                    {{ fieldErrors.reason[0] }}
                </div>
            </div>

            <!-- Notes -->
            <div class="col-12">
                <label class="form-label">Notes & Observations</label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    class="form-control"
                    placeholder="Enter technician notes, warranty claim info, or failure diagnostics…"
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
                :disabled="saving || inStockBatteries.length === 0"
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
import { getInStockBatteries, replaceBattery } from '../../api/batteries';
import { toDateInputValue } from '../../utils/datetime';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    vehicle: {
        type: Object,
        default: () => ({}),
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
const inStockBatteries = ref([]);

const oldBatterySerial = computed(() => {
    return props.currentInstallation?.battery?.serial_number
        || props.currentInstallation?.serial_number
        || '—';
});

const oldBatterySpecs = computed(() => {
    const b = props.currentInstallation?.battery || props.currentInstallation;
    if (!b) return '';
    const parts = [];
    if (b.brand) parts.push(b.brand);
    if (b.capacity_ah) parts.push(`${b.capacity_ah} Ah`);
    if (b.voltage) parts.push(b.voltage);
    return parts.join(' | ');
});

const currentStintKm = computed(() => {
    if (!props.currentInstallation) return null;
    const vOdo = Number(props.vehicle?.odometer_km ?? 0);
    const installKm = Number(props.currentInstallation.km_at_installation ?? 0);
    return Math.max(0, vOdo - installKm);
});

const form = ref({
    vehicle_id: '',
    old_battery_id: '',
    new_battery_id: '',
    slot_name: 'Main #1',
    replaced_at: toDateInputValue(),
    odometer_km: 0,
    reason: 'BATTERY_FAILED',
    notes: '',
});

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};

        form.value = {
            vehicle_id: props.vehicle?.id ?? '',
            old_battery_id: props.currentInstallation?.battery_id || props.currentInstallation?.battery?.id || props.currentInstallation?.id || '',
            new_battery_id: '',
            slot_name: props.currentInstallation?.slot_name || 'Main #1',
            replaced_at: toDateInputValue(),
            odometer_km: Number(props.vehicle?.odometer_km ?? 0),
            reason: 'BATTERY_FAILED',
            notes: '',
        };

        loadingStock.value = true;
        try {
            inStockBatteries.value = await getInStockBatteries();
        } catch {
            inStockBatteries.value = [];
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
    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const payload = { ...form.value };
        const result = await replaceBattery(payload);
        emit('replaced', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.message || 'Please fix the highlighted errors.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to replace battery.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
