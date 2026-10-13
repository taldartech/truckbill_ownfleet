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
            <i class="fas fa-plus-circle text-success me-2"></i>
            Install Battery on Truck
        </template>

        <div v-if="battery || vehicle" class="row g-2 mb-3">
            <div v-if="battery" class="col-sm">
                <div class="battery-modal-summary h-100">
                    <div class="battery-modal-summary__label">Battery</div>
                    <div class="fw-bold text-dark">
                        <i class="fas fa-car-battery text-warning me-1"></i>
                        {{ battery.serial_number }}
                        <span
                            v-if="battery.status === 'REMOVED'"
                            class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1"
                        >Standby</span>
                    </div>
                    <div class="text-muted small">
                        {{ [battery.brand, battery.capacity_ah ? `${Number(battery.capacity_ah)} Ah` : null, battery.voltage].filter(Boolean).join(' · ') }}
                    </div>
                </div>
            </div>
            <div v-if="vehicle" class="col-sm">
                <div class="battery-modal-summary h-100">
                    <div class="battery-modal-summary__label">Truck</div>
                    <div class="fw-bold text-dark">
                        <i class="fas fa-truck text-secondary me-1"></i>
                        {{ vehicle.registration_number }}
                    </div>
                    <div class="text-muted small">Odometer: {{ formatKm(vehicle.odometer_km) }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div v-if="!preselectedVehicle" class="col-md-6">
                <label class="form-label fw-semibold" for="batteryInstall_vehicle">
                    Truck <span class="text-danger">*</span>
                </label>
                <VehicleSelect
                    v-model="form.vehicle_id"
                    input-id="batteryInstall_vehicle"
                    :vehicles="vehicleList"
                    placeholder="Search and select truck"
                    :invalid="Boolean(fieldErrors.vehicle_id)"
                    :error-message="fieldErrors.vehicle_id?.[0] ?? ''"
                    :clearable="false"
                    @vehicle-selected="onVehicleSelected"
                />
            </div>

            <div v-if="!preselectedBattery" class="col-md-6">
                <label class="form-label fw-semibold" for="batteryInstall_battery">
                    Battery <span class="text-danger">*</span>
                </label>
                <AppSelect
                    v-model="form.battery_id"
                    input-id="batteryInstall_battery"
                    :options="availableBatteryOptions"
                    placeholder="Select battery from stock"
                    search-placeholder="Search serial no / brand…"
                    empty-message="No battery available in stock."
                    :clearable="false"
                />
                <div v-if="!loadingStock && availableBatteries.length === 0" class="form-text text-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No batteries in stock. Add one to inventory first.
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="batteryInstall_slot">
                    Slot / Position <span class="text-danger">*</span>
                </label>
                <input
                    id="batteryInstall_slot"
                    v-model="form.slot_name"
                    type="text"
                    list="battery-install-slot-presets"
                    class="form-control"
                    placeholder="e.g. Main #1"
                    maxlength="50"
                    :class="{ 'is-invalid': fieldErrors.slot_name || slotIsOccupied }"
                    required
                />
                <datalist id="battery-install-slot-presets">
                    <option v-for="slot in BATTERY_SLOT_PRESETS" :key="slot" :value="slot" />
                </datalist>
                <div v-if="fieldErrors.slot_name" class="invalid-feedback d-block">
                    {{ fieldErrors.slot_name[0] }}
                </div>
                <div v-else-if="slotIsOccupied" class="invalid-feedback d-block">
                    This slot already has a battery. Use Replace instead, or pick another slot.
                </div>
                <div v-if="occupiedSlots.length" class="form-text">
                    Occupied:
                    <span
                        v-for="slot in occupiedSlots"
                        :key="slot.slot_name"
                        class="badge bg-light text-dark border me-1"
                        :title="slot.serial"
                    >{{ slot.slot_name }}</span>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="batteryInstall_date">
                    Installation Date <span class="text-danger">*</span>
                </label>
                <input
                    id="batteryInstall_date"
                    v-model="form.installed_at"
                    type="date"
                    class="form-control"
                    :min="minInstallDate"
                    :max="todayDate"
                    :class="{ 'is-invalid': fieldErrors.installed_at }"
                    required
                />
                <div v-if="fieldErrors.installed_at" class="invalid-feedback d-block">
                    {{ fieldErrors.installed_at[0] }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="batteryInstall_km">Odometer at Installation</label>
                <div class="input-group">
                    <input
                        id="batteryInstall_km"
                        v-model.number="form.km_at_installation"
                        type="number"
                        min="0"
                        step="1"
                        class="form-control"
                        placeholder="Current truck odometer"
                        :class="{ 'is-invalid': fieldErrors.km_at_installation }"
                    />
                    <span class="input-group-text">KM</span>
                </div>
                <div v-if="fieldErrors.km_at_installation" class="invalid-feedback d-block">
                    {{ fieldErrors.km_at_installation[0] }}
                </div>
                <div v-else class="form-text">Leave blank to use the truck's current odometer.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="batteryInstall_notes">Notes</label>
                <textarea
                    id="batteryInstall_notes"
                    v-model="form.notes"
                    rows="2"
                    class="form-control"
                    maxlength="2000"
                    placeholder="Fitted by, invoice no, etc."
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
                class="btn btn-success px-4 d-flex align-items-center gap-2"
                :disabled="saving || !canSubmit"
            >
                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                <span>Install Battery</span>
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';
import VehicleSelect from '../ui/VehicleSelect.vue';
import { getInStockBatteries, getVehicleBatteries, installBattery } from '../../api/batteries';
import { listAllVehicles } from '../../api/vehicles';
import { toDateInputValue } from '../../utils/datetime';
import { BATTERY_SLOT_PRESETS, availableBatteryLabel, formatKm } from '../../constants/battery';
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
});

const emit = defineEmits(['close', 'installed']);

const saving = ref(false);
const loadingStock = ref(false);
const formError = ref('');
const fieldErrors = ref({});
const vehicleList = ref([]);
const availableBatteries = ref([]);
const occupiedSlots = ref([]);

const preselectedVehicle = computed(() => Boolean(props.vehicle?.id));
const preselectedBattery = computed(() => Boolean(props.battery?.id));
const todayDate = computed(() => toDateInputValue());

const form = ref(defaultForm());

function defaultForm() {
    return {
        vehicle_id: '',
        battery_id: '',
        slot_name: 'Main #1',
        installed_at: toDateInputValue(),
        km_at_installation: null,
        notes: '',
    };
}

const availableBatteryOptions = computed(() =>
    availableBatteries.value.map((b) => ({ value: b.id, label: availableBatteryLabel(b) })),
);

const selectedBattery = computed(() => {
    if (preselectedBattery.value) {
        return props.battery;
    }
    return availableBatteries.value.find((b) => b.id === form.value.battery_id) ?? null;
});

const minInstallDate = computed(() => selectedBattery.value?.purchase_date || undefined);

const slotIsOccupied = computed(() => {
    const slot = String(form.value.slot_name || '').trim().toLowerCase();
    return slot !== '' && occupiedSlots.value.some((s) => s.slot_name.toLowerCase() === slot);
});

const canSubmit = computed(() =>
    Boolean(form.value.vehicle_id && (form.value.battery_id || props.battery?.id) && !slotIsOccupied.value),
);

async function loadOccupiedSlots(vehicleId) {
    occupiedSlots.value = [];
    if (!vehicleId) {
        return;
    }
    try {
        const res = await getVehicleBatteries(vehicleId);
        occupiedSlots.value = res.active_batteries.map((inst) => ({
            slot_name: String(inst.slot_name || ''),
            serial: inst.battery?.serial_number ?? '',
        }));
        if (slotIsOccupied.value) {
            form.value.slot_name = BATTERY_SLOT_PRESETS.find(
                (preset) => !occupiedSlots.value.some((s) => s.slot_name.toLowerCase() === preset.toLowerCase()),
            ) ?? '';
        }
    } catch {
        occupiedSlots.value = [];
    }
}

function onVehicleSelected(vehicle) {
    form.value.km_at_installation = vehicle?.odometer_km != null ? Number(vehicle.odometer_km) : null;
    loadOccupiedSlots(vehicle?.id ?? '');
}

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};
        occupiedSlots.value = [];
        form.value = {
            ...defaultForm(),
            vehicle_id: props.vehicle?.id || '',
            battery_id: props.battery?.id || '',
            km_at_installation: props.vehicle?.odometer_km != null ? Number(props.vehicle.odometer_km) : null,
        };

        const loaders = [];

        if (preselectedVehicle.value) {
            loaders.push(loadOccupiedSlots(props.vehicle.id));
        } else {
            loaders.push(
                listAllVehicles({ activeOnly: true })
                    .then((list) => { vehicleList.value = list; })
                    .catch(() => { vehicleList.value = []; }),
            );
        }

        if (!preselectedBattery.value) {
            loadingStock.value = true;
            loaders.push(
                getInStockBatteries()
                    .then((list) => { availableBatteries.value = list; })
                    .catch(() => { availableBatteries.value = []; })
                    .finally(() => { loadingStock.value = false; }),
            );
        }

        await Promise.all(loaders);
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
        const targetBatteryId = form.value.battery_id || props.battery?.id;
        const payload = {
            vehicle_id: form.value.vehicle_id || props.vehicle?.id,
            slot_name: String(form.value.slot_name || '').trim(),
            installed_at: form.value.installed_at,
            km_at_installation: form.value.km_at_installation === '' ? null : form.value.km_at_installation,
            notes: form.value.notes || null,
        };

        const result = await installBattery(targetBatteryId, payload);
        emit('installed', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.errors.battery?.[0] || err.response.data.message || 'Please fix the errors below.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to install battery.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
