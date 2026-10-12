<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        size="md"
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="fas fa-plus-circle text-success me-2"></i>
            Install Battery on Truck
        </template>

        <!-- Battery Preview -->
        <div v-if="battery" class="p-3 mb-3 rounded-3 bg-light border">
            <div class="text-muted small text-uppercase fw-semibold">Battery to Install</div>
            <div class="fs-6 fw-bold text-dark mt-1">
                {{ battery.serial_number }}
                <span class="badge bg-primary ms-2">{{ battery.brand }}</span>
            </div>
            <div class="text-muted small">
                {{ battery.capacity_ah }} Ah | {{ battery.voltage }} | Purchased: {{ battery.purchase_date || '—' }}
            </div>
        </div>

        <div class="row g-3">
            <!-- Vehicle Selector (if not preselected) -->
            <div class="col-12" v-if="!preselectedVehicle">
                <label class="form-label fw-semibold">Select Truck <span class="text-danger">*</span></label>
                <select
                    v-model="form.vehicle_id"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.vehicle_id }"
                    required
                >
                    <option value="" disabled>-- Select Truck --</option>
                    <option
                        v-for="v in vehicleList"
                        :key="v.id"
                        :value="v.id"
                    >
                        {{ v.registration_number }} (Odometer: {{ Number(v.odometer_km || 0).toLocaleString() }} KM)
                    </option>
                </select>
                <div v-if="fieldErrors.vehicle_id" class="invalid-feedback d-block">
                    {{ fieldErrors.vehicle_id[0] }}
                </div>
            </div>

            <!-- In-Stock Battery Selector (if not preselected) -->
            <div class="col-12" v-if="!preselectedBattery">
                <label class="form-label fw-semibold">Select In-Stock Battery <span class="text-danger">*</span></label>
                <select
                    v-model="form.battery_id"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.battery_id }"
                    required
                >
                    <option value="" disabled>-- Select Battery --</option>
                    <option
                        v-for="b in inStockBatteries"
                        :key="b.id"
                        :value="b.id"
                    >
                        {{ b.serial_number }} - {{ b.brand }} ({{ b.capacity_ah }} Ah, {{ b.voltage }})
                    </option>
                </select>
                <div v-if="fieldErrors.battery_id" class="invalid-feedback d-block">
                    {{ fieldErrors.battery_id[0] }}
                </div>
            </div>

            <!-- Slot Name -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Slot / Position <span class="text-danger">*</span></label>
                <input
                    v-model="form.slot_name"
                    type="text"
                    list="battery-slot-presets"
                    class="form-control"
                    placeholder="e.g. Main #1, Slot 1"
                    :class="{ 'is-invalid': fieldErrors.slot_name }"
                    required
                />
                <datalist id="battery-slot-presets">
                    <option value="Main #1" />
                    <option value="Main #2" />
                    <option value="Slot 1 (Main)" />
                    <option value="Slot 2 (Auxiliary)" />
                    <option value="Reefer / Tail-lift" />
                </datalist>
                <div v-if="fieldErrors.slot_name" class="invalid-feedback d-block">
                    {{ fieldErrors.slot_name[0] }}
                </div>
            </div>

            <!-- Install Date -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Installation Date <span class="text-danger">*</span></label>
                <input
                    v-model="form.installed_at"
                    type="date"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.installed_at }"
                    required
                />
                <div v-if="fieldErrors.installed_at" class="invalid-feedback d-block">
                    {{ fieldErrors.installed_at[0] }}
                </div>
            </div>

            <!-- Odometer at Installation -->
            <div class="col-12">
                <label class="form-label fw-semibold">Odometer at Installation (KM)</label>
                <div class="input-group">
                    <input
                        v-model.number="form.km_at_installation"
                        type="number"
                        min="0"
                        class="form-control"
                        placeholder="Current truck odometer"
                        :class="{ 'is-invalid': fieldErrors.km_at_installation }"
                    />
                    <span class="input-group-text">KM</span>
                </div>
                <div v-if="fieldErrors.km_at_installation" class="invalid-feedback d-block">
                    {{ fieldErrors.km_at_installation[0] }}
                </div>
            </div>

            <!-- Notes -->
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    class="form-control"
                    placeholder="Additional details regarding installation…"
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
                :disabled="saving"
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
import { getInStockBatteries, installBattery } from '../../api/batteries';
import { listVehicles } from '../../api/vehicles';
import { toDateInputValue } from '../../utils/datetime';

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
const formError = ref('');
const fieldErrors = ref({});
const vehicleList = ref([]);
const inStockBatteries = ref([]);

const preselectedVehicle = computed(() => Boolean(props.vehicle?.id));
const preselectedBattery = computed(() => Boolean(props.battery?.id));

const form = ref({
    vehicle_id: '',
    battery_id: '',
    slot_name: 'Main #1',
    installed_at: toDateInputValue(),
    km_at_installation: null,
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
            vehicle_id: props.vehicle?.id || '',
            battery_id: props.battery?.id || '',
            slot_name: 'Main #1',
            installed_at: toDateInputValue(),
            km_at_installation: props.vehicle?.odometer_km ? Number(props.vehicle.odometer_km) : null,
            notes: '',
        };

        if (!preselectedVehicle.value) {
            try {
                const res = await listVehicles({ perPage: 100, is_active: true });
                vehicleList.value = res.data ?? [];
            } catch {
                vehicleList.value = [];
            }
        }

        if (!preselectedBattery.value) {
            try {
                inStockBatteries.value = await getInStockBatteries();
            } catch {
                inStockBatteries.value = [];
            }
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
        const targetBatteryId = form.value.battery_id || props.battery?.id;
        const payload = {
            vehicle_id: form.value.vehicle_id || props.vehicle?.id,
            slot_name: form.value.slot_name,
            installed_at: form.value.installed_at,
            km_at_installation: form.value.km_at_installation,
            notes: form.value.notes,
        };

        const result = await installBattery(targetBatteryId, payload);
        emit('installed', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.message || 'Please fix the errors below.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to install battery.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
