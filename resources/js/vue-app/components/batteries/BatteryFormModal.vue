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
            <i class="fas fa-car-battery text-warning me-2"></i>
            {{ isEdit ? 'Edit Battery' : 'Add Battery' }}
        </template>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Battery No. / Serial No. <span class="text-danger">*</span></label>
                <input
                    v-model="form.serial_number"
                    type="text"
                    class="form-control text-uppercase"
                    placeholder="e.g. BAT-00125"
                    :class="{ 'is-invalid': fieldErrors.serial_number }"
                    required
                />
                <div v-if="fieldErrors.serial_number" class="invalid-feedback d-block">
                    {{ fieldErrors.serial_number[0] }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                <input
                    v-model="form.brand"
                    type="text"
                    list="battery-brand-list"
                    class="form-control"
                    placeholder="e.g. Exide, Amaron, Tata Green"
                    :class="{ 'is-invalid': fieldErrors.brand }"
                    required
                />
                <datalist id="battery-brand-list">
                    <option value="Exide" />
                    <option value="Amaron" />
                    <option value="Tata Green" />
                    <option value="Luminous" />
                    <option value="SF Sonic" />
                    <option value="Livguard" />
                    <option value="PowerZone" />
                </datalist>
                <div v-if="fieldErrors.brand" class="invalid-feedback d-block">
                    {{ fieldErrors.brand[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Model</label>
                <input
                    v-model="form.model"
                    type="text"
                    class="form-control"
                    placeholder="e.g. Mileage, Hi-Life"
                    :class="{ 'is-invalid': fieldErrors.model }"
                />
                <div v-if="fieldErrors.model" class="invalid-feedback d-block">
                    {{ fieldErrors.model[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Type</label>
                <select
                    v-model="form.battery_type"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.battery_type }"
                >
                    <option value="LEAD_ACID">Lead Acid</option>
                    <option value="MAINTENANCE_FREE">Maintenance Free (MF)</option>
                    <option value="TUBULAR">Tubular</option>
                    <option value="LITHIUM_ION">Lithium-Ion</option>
                    <option value="GEL">Gel</option>
                </select>
                <div v-if="fieldErrors.battery_type" class="invalid-feedback d-block">
                    {{ fieldErrors.battery_type[0] }}
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label">Voltage</label>
                <select
                    v-model="form.voltage"
                    class="form-select"
                    :class="{ 'is-invalid': fieldErrors.voltage }"
                >
                    <option value="12V">12V</option>
                    <option value="24V">24V</option>
                    <option value="6V">6V</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">Capacity (Ah) <span class="text-danger">*</span></label>
                <input
                    v-model.number="form.capacity_ah"
                    type="number"
                    min="1"
                    max="1000"
                    step="1"
                    class="form-control"
                    placeholder="150"
                    :class="{ 'is-invalid': fieldErrors.capacity_ah }"
                    required
                />
                <div v-if="fieldErrors.capacity_ah" class="invalid-feedback d-block">
                    {{ fieldErrors.capacity_ah[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Purchase Date <span class="text-danger">*</span></label>
                <input
                    v-model="form.purchase_date"
                    type="date"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.purchase_date }"
                    required
                />
                <div v-if="fieldErrors.purchase_date" class="invalid-feedback d-block">
                    {{ fieldErrors.purchase_date[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Purchase Cost (₹)</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input
                        v-model.number="form.purchase_cost"
                        type="number"
                        min="0"
                        step="100"
                        class="form-control"
                        placeholder="14500"
                        :class="{ 'is-invalid': fieldErrors.purchase_cost }"
                    />
                </div>
                <div v-if="fieldErrors.purchase_cost" class="invalid-feedback d-block">
                    {{ fieldErrors.purchase_cost[0] }}
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Warranty Period (Months)</label>
                <div class="input-group">
                    <input
                        v-model.number="form.warranty_months"
                        type="number"
                        min="0"
                        max="120"
                        class="form-control"
                        placeholder="24"
                        :class="{ 'is-invalid': fieldErrors.warranty_months }"
                    />
                    <span class="input-group-text">Months</span>
                </div>
                <div v-if="fieldErrors.warranty_months" class="invalid-feedback d-block">
                    {{ fieldErrors.warranty_months[0] }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Supplier / Dealer</label>
                <input
                    v-model="form.supplier_name"
                    type="text"
                    class="form-control"
                    placeholder="e.g. Authorized Exide Dealer"
                    :class="{ 'is-invalid': fieldErrors.supplier_name }"
                />
            </div>

            <div class="col-md-6">
                <label class="form-label">Expected Life (Months)</label>
                <div class="input-group">
                    <input
                        v-model.number="form.expected_life_months"
                        type="number"
                        min="1"
                        max="240"
                        class="form-control"
                        placeholder="36"
                        :class="{ 'is-invalid': fieldErrors.expected_life_months }"
                    />
                    <span class="input-group-text">Months</span>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Notes / Remarks</label>
                <textarea
                    v-model="form.notes"
                    rows="2"
                    class="form-control"
                    placeholder="Any specific comments or warranty remarks…"
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
                :disabled="saving"
            >
                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                <span>{{ isEdit ? 'Save Changes' : 'Add Battery' }}</span>
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import { createBattery, updateBattery } from '../../api/batteries';
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
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => Boolean(props.battery?.id));
const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});

const form = ref(defaultForm());

function defaultForm() {
    return {
        serial_number: '',
        brand: 'Exide',
        model: '',
        battery_type: 'LEAD_ACID',
        voltage: '12V',
        capacity_ah: 150,
        purchase_date: toDateInputValue(),
        purchase_cost: null,
        warranty_months: 24,
        expected_life_months: 36,
        supplier_name: '',
        notes: '',
    };
}

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        formError.value = '';
        fieldErrors.value = {};

        if (props.battery) {
            form.value = {
                serial_number: props.battery.serial_number ?? '',
                brand: props.battery.brand ?? '',
                model: props.battery.model ?? '',
                battery_type: props.battery.battery_type ?? 'LEAD_ACID',
                voltage: props.battery.voltage ?? '12V',
                capacity_ah: props.battery.capacity_ah ?? 150,
                purchase_date: props.battery.purchase_date ?? toDateInputValue(),
                purchase_cost: props.battery.purchase_cost ?? null,
                warranty_months: props.battery.warranty_months ?? 24,
                expected_life_months: props.battery.expected_life_months ?? 36,
                supplier_name: props.battery.supplier_name ?? '',
                notes: props.battery.notes ?? '',
            };
        } else {
            form.value = defaultForm();
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
        let result;
        if (isEdit.value) {
            result = await updateBattery(props.battery.id, payload);
        } else {
            result = await createBattery(payload);
        }
        emit('saved', result);
        emit('close');
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            fieldErrors.value = err.response.data.errors;
            formError.value = err.response.data.message || 'Please fix the highlighted errors.';
        } else {
            formError.value = err.response?.data?.message || 'Failed to save battery record.';
        }
    } finally {
        saving.value = false;
    }
}
</script>
