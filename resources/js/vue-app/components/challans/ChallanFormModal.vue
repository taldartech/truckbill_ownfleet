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
            <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-receipt'"></i>
            {{ isEdit ? 'Edit Challan' : 'Add Challan' }}
        </template>

        <div class="row g-2 g-sm-3 mb-2 mb-sm-3">
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_challan_number`" class="form-label">Challan Number *</label>
                <input
                    :id="`${modalId}_challan_number`"
                    v-model="form.challan_number"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.challan_number }"
                    required
                />
                <div v-if="fieldErrors.challan_number" class="invalid-feedback d-block">
                    {{ fieldErrors.challan_number[0] }}
                </div>
            </div>
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_challan_date`" class="form-label">Date *</label>
                <AppDateInput
                    :input-id="`${modalId}_challan_date`"
                    v-model="form.challan_date"
                    :invalid="!!fieldErrors.challan_date"
                    required
                />
                <div v-if="fieldErrors.challan_date" class="invalid-feedback d-block">
                    {{ fieldErrors.challan_date[0] }}
                </div>
            </div>
        </div>

        <div class="row g-2 g-sm-3 mb-2 mb-sm-3">
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_vehicle`" class="form-label">Truck *</label>
                <VehicleSelect
                    :input-id="`${modalId}_vehicle`"
                    v-model="form.vehicle_id"
                    :vehicles="baseVehicleOptions"
                    :lookup-vehicle="challan?.vehicle ?? null"
                    placeholder="Select truck"
                    :invalid="Boolean(fieldErrors.vehicle_id)"
                    :error-message="fieldErrors.vehicle_id?.[0] ?? ''"
                />
                <div v-if="fieldErrors.vehicle_id" class="invalid-feedback d-block">
                    {{ fieldErrors.vehicle_id[0] }}
                </div>
            </div>
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_driver`" class="form-label">Driver *</label>
                <AppSelect
                    :input-id="`${modalId}_driver`"
                    v-model="form.driver_id"
                    :options="driverSelectOptions"
                    placeholder="Select driver"
                    search-placeholder="Search driver…"
                    :invalid="Boolean(fieldErrors.driver_id)"
                    :error-message="fieldErrors.driver_id?.[0] ?? ''"
                />
                <div v-if="fieldErrors.driver_id" class="invalid-feedback d-block">
                    {{ fieldErrors.driver_id[0] }}
                </div>
            </div>
        </div>

        <div class="row g-2 g-sm-3 mb-2 mb-sm-3">
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_violation_type`" class="form-label">Violation Type *</label>
                <input
                    :id="`${modalId}_violation_type`"
                    v-model="form.violation_type"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.violation_type }"
                    placeholder="e.g. Over speeding"
                    required
                />
                <div v-if="fieldErrors.violation_type" class="invalid-feedback d-block">
                    {{ fieldErrors.violation_type[0] }}
                </div>
            </div>
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_amount`" class="form-label">Amount (₹) *</label>
                <input
                    :id="`${modalId}_amount`"
                    v-model="form.amount"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.amount }"
                    required
                />
                <div v-if="fieldErrors.amount" class="invalid-feedback d-block">
                    {{ fieldErrors.amount[0] }}
                </div>
            </div>
        </div>

        <div class="row g-2 g-sm-3 mb-2 mb-sm-3">
            <div class="col-6 col-md-6">
                <label :for="`${modalId}_status`" class="form-label">Payment Status *</label>
                <AppSelect
                    :input-id="`${modalId}_status`"
                    v-model="form.status"
                    :options="challanStatusOptions"
                    placeholder="Select status"
                    search-placeholder="Search status…"
                    :invalid="Boolean(fieldErrors.status)"
                    :error-message="fieldErrors.status?.[0] ?? ''"
                />
                <div v-if="fieldErrors.status" class="invalid-feedback d-block">
                    {{ fieldErrors.status[0] }}
                </div>
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
            <button type="submit" class="btn" :class="isEdit ? 'btn-warning' : 'btn-success'" :disabled="saving">
                <i class="bi bi-check-circle"></i>
                {{
                    saving
                        ? isEdit
                            ? 'Updating...'
                            : 'Adding...'
                        : isEdit
                          ? 'Update Challan'
                          : 'Add Challan'
                }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { listAllDrivers } from '../../api/drivers';
import { listAllVehicles } from '../../api/vehicles';
import AppModal from '../ui/AppModal.vue';
import VehicleSelect from '../ui/VehicleSelect.vue';
import AppSelect from '../ui/AppSelect.vue';
import { mergeLookupOption } from '../../utils/mergeLookupOption';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    challan: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.mode === 'edit');

const challanStatusOptions = [
    { value: 'UNPAID', label: 'Unpaid' },
    { value: 'PAID', label: 'Paid' },
];

const driverSelectOptions = computed(() =>
    drivers.value.map((driver) => ({
        id: driver.id,
        label: driver.name,
        is_active: driver.is_active,
    })),
);
const drivers = ref([]);
const baseVehicleOptions = ref([]);
const baseDriverOptions = ref([]);

const form = ref(emptyForm());

function emptyForm() {
    return {
        challan_number: '',
        challan_date: '',
        vehicle_id: '',
        driver_id: '',
        violation_type: '',
        amount: '',
        status: 'UNPAID',
    };
}

function fillForm(challan) {
    form.value = {
        challan_number: challan?.challan_number ?? '',
        challan_date: challan?.challan_date ?? '',
        vehicle_id: challan?.vehicle_id ?? '',
        driver_id: challan?.driver_id ?? '',
        violation_type: challan?.violation_type ?? '',
        amount: challan?.amount !== null && challan?.amount !== undefined ? String(challan.amount) : '',
        status: challan?.status ?? 'UNPAID',
    };
}

function refreshLookupOptions(challan = props.challan) {
    drivers.value = mergeLookupOption(baseDriverOptions.value, challan?.driver ?? null);
}

async function loadOptions() {
    const [vehicleList, driverList] = await Promise.all([listAllVehicles(), listAllDrivers({ activeOnly: true })]);

    baseVehicleOptions.value = vehicleList;
    baseDriverOptions.value = driverList;
    refreshLookupOptions(props.challan);
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            await loadOptions();
            fillForm(props.challan);
        }
    },
);

watch(
    () => props.challan,
    (challan) => {
        if (!props.show) {
            return;
        }

        fillForm(challan);
        refreshLookupOptions(challan);
    },
);

function close() {
    emit('close');
}

function submit() {
    const amount = form.value.amount === '' ? Number.NaN : Number(form.value.amount);

    if (Number.isNaN(amount) || amount < 0) {
        return;
    }

    emit('submit', {
        challan_number: form.value.challan_number.trim(),
        challan_date: form.value.challan_date,
        vehicle_id: form.value.vehicle_id,
        driver_id: form.value.driver_id,
        violation_type: form.value.violation_type.trim(),
        amount,
        status: form.value.status,
    });
}
</script>
