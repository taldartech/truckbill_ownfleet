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
            <i class="fas fa-stethoscope text-primary me-2"></i>
            Log Health Check
        </template>

        <div v-if="battery" class="battery-modal-summary mb-3">
            <div class="battery-modal-summary__label">Battery</div>
            <div class="fw-bold text-dark">
                <i class="fas fa-car-battery text-warning me-1"></i>
                {{ battery.serial_number }}
                <span class="text-muted fw-normal small ms-1">
                    {{ [battery.brand, battery.voltage].filter(Boolean).join(' · ') }}
                </span>
            </div>
            <div v-if="battery.active_installation" class="text-muted small">
                On {{ battery.active_installation.vehicle_registration }} · Slot {{ battery.active_installation.slot_name }}
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="batteryInspection_date">
                    Check Date <span class="text-danger">*</span>
                </label>
                <input
                    id="batteryInspection_date"
                    v-model="form.inspected_at"
                    type="date"
                    class="form-control"
                    :min="battery?.purchase_date || undefined"
                    :max="todayDate"
                    :class="{ 'is-invalid': fieldErrors.inspected_at }"
                    required
                />
                <div v-if="fieldErrors.inspected_at" class="invalid-feedback d-block">
                    {{ fieldErrors.inspected_at[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="batteryInspection_voltage">Voltage Reading</label>
                <div class="input-group">
                    <input
                        id="batteryInspection_voltage"
                        v-model.number="form.voltage_reading"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        placeholder="12.60"
                        :class="{ 'is-invalid': fieldErrors.voltage_reading }"
                    />
                    <span class="input-group-text">V</span>
                </div>
                <div v-if="fieldErrors.voltage_reading" class="invalid-feedback d-block">
                    {{ fieldErrors.voltage_reading[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="batteryInspection_gravity">Specific Gravity</label>
                <input
                    id="batteryInspection_gravity"
                    v-model.number="form.specific_gravity"
                    type="number"
                    min="1"
                    max="1.4"
                    step="0.001"
                    class="form-control"
                    placeholder="1.250"
                    :class="{ 'is-invalid': fieldErrors.specific_gravity }"
                />
                <div v-if="fieldErrors.specific_gravity" class="invalid-feedback d-block">
                    {{ fieldErrors.specific_gravity[0] }}
                </div>
                <div v-else class="form-text">Flooded / tubular batteries only.</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold d-block">
                    Condition <span class="text-danger">*</span>
                </label>
                <div class="btn-group flex-wrap" role="group" aria-label="Battery condition">
                    <template v-for="opt in BATTERY_CONDITION_OPTIONS" :key="opt.value">
                        <input
                            :id="`batteryInspection_condition_${opt.value}`"
                            v-model="form.condition"
                            type="radio"
                            class="btn-check"
                            name="batteryInspection_condition"
                            :value="opt.value"
                            autocomplete="off"
                        />
                        <label
                            class="btn btn-sm"
                            :class="form.condition === opt.value ? conditionActiveClass(opt.value) : 'btn-outline-secondary'"
                            :for="`batteryInspection_condition_${opt.value}`"
                        >
                            {{ opt.label }}
                        </label>
                    </template>
                </div>
                <div v-if="fieldErrors.condition" class="invalid-feedback d-block">
                    {{ fieldErrors.condition[0] }}
                </div>
                <div v-if="voltageHint" class="form-text">
                    <i class="fas fa-lightbulb text-warning me-1"></i>{{ voltageHint }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="batteryInspection_action">Action Taken</label>
                <input
                    id="batteryInspection_action"
                    v-model="form.action_taken"
                    type="text"
                    list="battery-inspection-actions"
                    class="form-control"
                    maxlength="255"
                    placeholder="e.g. Terminals cleaned"
                />
                <datalist id="battery-inspection-actions">
                    <option value="No action needed" />
                    <option value="Terminals cleaned" />
                    <option value="Topped up distilled water" />
                    <option value="Recharged" />
                    <option value="Sent for warranty check" />
                    <option value="Recommend replacement" />
                </datalist>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="batteryInspection_notes">Notes</label>
                <textarea
                    id="batteryInspection_notes"
                    v-model="form.notes"
                    rows="1"
                    class="form-control"
                    maxlength="2000"
                    placeholder="Observations"
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
                :disabled="saving"
            >
                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                <span>Save Health Check</span>
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import { createBatteryInspection } from '../../api/batteries';
import { toDateInputValue } from '../../utils/datetime';
import { BATTERY_CONDITION_OPTIONS } from '../../constants/battery';
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
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});

const todayDate = computed(() => toDateInputValue());

const form = ref(defaultForm());

function defaultForm() {
    return {
        inspected_at: toDateInputValue(),
        voltage_reading: null,
        specific_gravity: null,
        condition: 'GOOD',
        action_taken: '',
        notes: '',
    };
}

const voltageHint = computed(() => {
    const reading = Number(form.value.voltage_reading);
    if (!reading || !String(props.battery?.voltage ?? '12V').startsWith('12')) {
        return '';
    }
    if (reading >= 12.5) {
        return 'Resting voltage of 12.5 V or more is a healthy, fully charged 12 V battery.';
    }
    if (reading >= 12.2) {
        return 'Around 12.2–12.4 V usually means partially charged — keep an eye on it.';
    }
    return 'Below 12.2 V at rest usually means a weak or discharged battery.';
});

function conditionActiveClass(value) {
    return {
        GOOD: 'btn-success',
        FAIR: 'btn-info',
        WEAK: 'btn-warning',
        FAILED: 'btn-danger',
    }[value] ?? 'btn-primary';
}

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};
        form.value = defaultForm();
    },
);

function close() {
    if (!saving.value) {
        emit('close');
    }
}

async function submit() {
    if (!props.battery?.id) {
        return;
    }

    saving.value = true;
    formError.value = '';
    fieldErrors.value = {};

    try {
        const result = await createBatteryInspection(props.battery.id, {
            inspected_at: form.value.inspected_at,
            voltage_reading: form.value.voltage_reading === '' ? null : form.value.voltage_reading,
            specific_gravity: form.value.specific_gravity === '' ? null : form.value.specific_gravity,
            condition: form.value.condition,
            action_taken: form.value.action_taken || null,
            notes: form.value.notes || null,
        });
        emit('saved', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.errors.battery?.[0] || err.response.data.message || 'Please fix the highlighted errors.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to save health check.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
