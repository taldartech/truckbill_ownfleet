<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-wrench-adjustable'"></i>
                        {{ isEdit ? 'Edit Service Record' : 'Add Service Record' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="displayFormError" class="alert alert-danger py-2">{{ displayFormError }}</div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_vehicle`" class="form-label">Truck *</label>
                                <SearchableSelect
                                    :input-id="`${modalId}_vehicle`"
                                    v-model="form.vehicle_id"
                                    :items="vehicleSelectOptions"
                                    placeholder="Select truck"
                                    search-placeholder="Search truck number…"
                                    empty-message="No truck found."
                                    item-icon-class="bi bi-truck"
                                    attach-dropdown-to-body
                                    :disabled="lockVehicle"
                                    :invalid="Boolean(displayFieldErrors.vehicle_id)"
                                    :error-message="displayFieldErrors.vehicle_id?.[0] ?? ''"
                                />
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_service_date`" class="form-label">Service Date *</label>
                                <input
                                    :id="`${modalId}_service_date`"
                                    v-model="form.service_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': displayFieldErrors.service_date }"
                                    :max="today"
                                    required
                                />
                                <div v-if="displayFieldErrors.service_date" class="invalid-feedback d-block">
                                    {{ displayFieldErrors.service_date[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_odometer_km`" class="form-label">Meter Reading (KM) *</label>
                                <input
                                    :id="`${modalId}_odometer_km`"
                                    v-model="form.odometer_km"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': displayFieldErrors.odometer_km }"
                                    required
                                />
                                <div v-if="displayFieldErrors.odometer_km" class="invalid-feedback d-block">
                                    {{ displayFieldErrors.odometer_km[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_service_type`" class="form-label">Service Type *</label>
                                <SearchableSelect
                                    :input-id="`${modalId}_service_type`"
                                    v-model="form.maintenance_service_type_id"
                                    :items="serviceTypeSelectItems"
                                    placeholder="Select type"
                                    search-placeholder="Search service type…"
                                    empty-message="No service type found."
                                    item-icon-class="bi bi-wrench-adjustable"
                                    attach-dropdown-to-body
                                    :invalid="Boolean(displayFieldErrors.maintenance_service_type_id)"
                                    :error-message="displayFieldErrors.maintenance_service_type_id?.[0] ?? ''"
                                />
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_service_provider`" class="form-label">Service Provider *</label>
                                <input
                                    :id="`${modalId}_service_provider`"
                                    v-model="form.service_provider"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': displayFieldErrors.service_provider }"
                                    placeholder="Garage / workshop name"
                                    required
                                />
                                <div v-if="displayFieldErrors.service_provider" class="invalid-feedback d-block">
                                    {{ displayFieldErrors.service_provider[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_cost_amount`" class="form-label">Cost Amount (₹) *</label>
                                <input
                                    :id="`${modalId}_cost_amount`"
                                    v-model="form.cost_amount"
                                    v-no-scroll-change
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': displayFieldErrors.cost_amount }"
                                    required
                                />
                                <div v-if="displayFieldErrors.cost_amount" class="invalid-feedback d-block">
                                    {{ displayFieldErrors.cost_amount[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_next_service_due_km`" class="form-label">Next Due KM</label>
                                <input
                                    :id="`${modalId}_next_service_due_km`"
                                    v-model="form.next_service_due_km"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': displayFieldErrors.next_service_due_km }"
                                />
                                <div v-if="displayFieldErrors.next_service_due_km" class="invalid-feedback d-block">
                                    {{ displayFieldErrors.next_service_due_km[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_next_service_due_date`" class="form-label">Next Due Date</label>
                                <input
                                    :id="`${modalId}_next_service_due_date`"
                                    v-model="form.next_service_due_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': displayFieldErrors.next_service_due_date }"
                                    :min="minNextDueDate"
                                    :disabled="!form.service_date"
                                />
                                <div v-if="displayFieldErrors.next_service_due_date" class="invalid-feedback d-block">
                                    {{ displayFieldErrors.next_service_due_date[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label :for="`${modalId}_remarks`" class="form-label">Remarks</label>
                            <textarea
                                :id="`${modalId}_remarks`"
                                v-model="form.remarks"
                                class="form-control"
                                :class="{ 'is-invalid': displayFieldErrors.remarks }"
                                rows="3"
                            ></textarea>
                            <div v-if="displayFieldErrors.remarks" class="invalid-feedback d-block">
                                {{ displayFieldErrors.remarks[0] }}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-success" :disabled="saving">
                            <i class="bi bi-check-circle"></i>
                            {{
                                saving
                                    ? isEdit
                                        ? 'Updating...'
                                        : 'Adding...'
                                    : isEdit
                                      ? 'Update Record'
                                      : 'Add Record'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { buildVehicleSelectOptions, listAllVehicles } from '../../api/vehicles';
import { buildMaintenanceServiceTypeSelectOptions, listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import SearchableSelect from '../ui/SearchableSelect.vue';
import { toDateInputValue } from '../../utils/datetime';
import { mergeLookupOption } from '../../utils/mergeLookupOption';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    record: { type: Object, default: null },
    presetVehicleId: { type: String, default: '' },
    lockVehicle: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let bootstrapModal = null;

const isEdit = computed(() => props.mode === 'edit');
const today = computed(() => toDateInputValue());
const minNextDueDate = computed(() => dayAfter(form.value.service_date));
const serviceTypes = ref([]);
const serviceTypeSelectItems = computed(() =>
    serviceTypeOptions.value.map((type) => ({
        id: type.value,
        label: type.label,
        hint: type.subtitle ?? '',
        code: type.subtitle ?? '',
        icon: 'bi bi-wrench-adjustable',
    })),
);
const serviceTypeOptions = computed(() => {
    const options = buildMaintenanceServiceTypeSelectOptions(serviceTypes.value);

    if (!props.record?.maintenance_service_type_id) {
        return options;
    }

    const currentId = String(props.record.maintenance_service_type_id);
    const hasCurrent = options.some((option) => option.value === currentId);

    if (hasCurrent) {
        return options;
    }

    return [
        ...options,
        {
            value: currentId,
            label: props.record.service_type_label ?? props.record.service_type ?? 'Selected type',
        },
    ];
});

const baseVehicleOptions = ref([]);
const clientErrors = ref({});
const clientFormError = ref('');
const vehicleSelectOptions = computed(() =>
    buildVehicleSelectOptions(mergeLookupOption(baseVehicleOptions.value, props.record?.vehicle ?? null)),
);
const displayFieldErrors = computed(() => ({
    ...clientErrors.value,
    ...props.fieldErrors,
}));
const displayFormError = computed(() => props.formError || clientFormError.value);
const form = ref(emptyForm());

function dayAfter(dateString) {
    if (!dateString) {
        return '';
    }

    const date = new Date(`${dateString}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    date.setDate(date.getDate() + 1);

    return toDateInputValue(date);
}

function emptyForm() {
    return {
        vehicle_id: '',
        service_date: '',
        odometer_km: '',
        maintenance_service_type_id: '',
        service_provider: '',
        cost_amount: '',
        next_service_due_km: '',
        next_service_due_date: '',
        remarks: '',
    };
}

function clearClientErrors() {
    clientErrors.value = {};
    clientFormError.value = '';
}

function clearClientFieldError(field) {
    if (!(field in clientErrors.value)) {
        return;
    }

    const next = { ...clientErrors.value };
    delete next[field];
    clientErrors.value = next;

    if (Object.keys(clientErrors.value).length === 0) {
        clientFormError.value = '';
    }
}

function fillForm(record) {
    clearClientErrors();
    form.value = {
        vehicle_id: record?.vehicle_id ?? props.presetVehicleId ?? '',
        service_date: record?.service_date ?? '',
        odometer_km:
            record?.odometer_km !== null && record?.odometer_km !== undefined ? String(record.odometer_km) : '',
        maintenance_service_type_id:
            record?.maintenance_service_type_id !== null && record?.maintenance_service_type_id !== undefined
                ? String(record.maintenance_service_type_id)
                : '',
        service_provider: record?.service_provider ?? '',
        cost_amount:
            record?.cost_amount !== null && record?.cost_amount !== undefined ? String(record.cost_amount) : '',
        next_service_due_km:
            record?.next_service_due_km !== null && record?.next_service_due_km !== undefined
                ? String(record.next_service_due_km)
                : '',
        next_service_due_date: record?.next_service_due_date ?? '',
        remarks: record?.remarks ?? '',
    };
}

async function loadOptions() {
    const [vehicles, types] = await Promise.all([listAllVehicles(), listMaintenanceServiceTypes()]);

    baseVehicleOptions.value = vehicles;
    serviceTypes.value = types;
}

watch(
    () => form.value.vehicle_id,
    () => clearClientFieldError('vehicle_id'),
);

watch(
    () => form.value.service_date,
    (serviceDate) => {
        clearClientFieldError('service_date');
        clearClientFieldError('next_service_due_date');

        if (!serviceDate) {
            form.value.next_service_due_date = '';

            return;
        }

        if (form.value.next_service_due_date && form.value.next_service_due_date <= serviceDate) {
            form.value.next_service_due_date = '';
        }
    },
);

watch(
    () => form.value.odometer_km,
    () => clearClientFieldError('odometer_km'),
);

watch(
    () => form.value.maintenance_service_type_id,
    () => clearClientFieldError('maintenance_service_type_id'),
);

watch(
    () => form.value.service_provider,
    () => clearClientFieldError('service_provider'),
);

watch(
    () => form.value.cost_amount,
    () => clearClientFieldError('cost_amount'),
);

watch(
    () => form.value.next_service_due_date,
    () => clearClientFieldError('next_service_due_date'),
);

watch(
    () => props.fieldErrors,
    (errors) => {
        if (Object.keys(errors).length > 0) {
            clearClientErrors();
        }
    },
    { deep: true },
);

watch(
    () => props.show,
    async (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false, focus: false });
        }

        if (visible) {
            await loadOptions();
            fillForm(props.record);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

watch(
    () => props.record,
    (record) => {
        if (!props.show) {
            return;
        }

        fillForm(record);
    },
);

function close() {
    emit('close');
}

function optionalNumber(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);

    return Number.isNaN(parsed) ? null : parsed;
}

function validateClientForm() {
    clearClientErrors();

    const errors = {};

    if (!form.value.vehicle_id) {
        errors.vehicle_id = ['Please select a truck.'];
    }

    if (!form.value.service_date) {
        errors.service_date = ['Service date is required.'];
    } else if (form.value.service_date > today.value) {
        errors.service_date = ['Service date cannot be in the future.'];
    }

    if (form.value.odometer_km === '' || form.value.odometer_km === null || form.value.odometer_km === undefined) {
        errors.odometer_km = ['Meter reading is required.'];
    } else {
        const odometer = optionalNumber(form.value.odometer_km);

        if (odometer === null || odometer < 0) {
            errors.odometer_km = ['Meter reading must be 0 or greater.'];
        }
    }

    if (!form.value.maintenance_service_type_id) {
        errors.maintenance_service_type_id = ['Service type is required.'];
    }

    if (!form.value.service_provider.trim()) {
        errors.service_provider = ['Service provider is required.'];
    }

    if (form.value.cost_amount === '' || form.value.cost_amount === null || form.value.cost_amount === undefined) {
        errors.cost_amount = ['Cost amount is required.'];
    } else {
        const costAmount = optionalNumber(form.value.cost_amount);

        if (costAmount === null || costAmount <= 0) {
            errors.cost_amount = ['Cost amount must be greater than 0.'];
        }
    }

    if (
        form.value.next_service_due_date &&
        form.value.service_date &&
        form.value.next_service_due_date <= form.value.service_date
    ) {
        errors.next_service_due_date = ['Next due date must be after service date.'];
    }

    if (Object.keys(errors).length > 0) {
        clientErrors.value = errors;
        clientFormError.value = 'Please fix the highlighted fields.';

        return false;
    }

    return true;
}

function submit() {
    if (!validateClientForm()) {
        return;
    }

    const odometer = optionalNumber(form.value.odometer_km);
    const costAmount = optionalNumber(form.value.cost_amount);

    emit('submit', {
        vehicle_id: form.value.vehicle_id,
        service_date: form.value.service_date,
        odometer_km: odometer,
        maintenance_service_type_id: Number(form.value.maintenance_service_type_id),
        service_provider: form.value.service_provider.trim(),
        cost_amount: costAmount,
        next_service_due_km: optionalNumber(form.value.next_service_due_km),
        next_service_due_date: form.value.next_service_due_date || null,
        remarks: form.value.remarks.trim() || null,
    });
}
</script>
