<template>
    <div class="gst-details-section">
        <div v-if="showInheritCheckbox" class="mb-3">
            <div class="form-check">
                <input
                    id="inherit_company_gst"
                    class="form-check-input"
                    type="checkbox"
                    :checked="inheritCompanyGst"
                    :disabled="readonly"
                    @change="onInheritChange"
                />
                <label class="form-check-label" for="inherit_company_gst">
                    Inherit GST settings from company
                </label>
            </div>
            <div v-if="inheritCompanyGst" class="form-text">
                Branch uses the company GST profile shown below.
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="gst_registration_type">GST Registration Type</label>
                <AppSelect
                    input-id="gst_registration_type"
                    :model-value="effectiveModel.registration_type"
                    :options="GST_REGISTRATION_OPTIONS"
                    placeholder="Select registration type"
                    search-placeholder="Search type…"
                    :disabled="isFieldReadonly"
                    :invalid="Boolean(fieldErrors.registration_type)"
                    :error-message="fieldErrors.registration_type?.[0] ?? ''"
                    @update:model-value="updateField('registration_type', $event)"
                />
            </div>

            <div v-if="showTaxLiability" class="col-md-6">
                <label class="form-label" for="gst_tax_liability">Tax Liability</label>
                <AppSelect
                    input-id="gst_tax_liability"
                    :model-value="effectiveModel.tax_liability"
                    :options="TAX_LIABILITY_OPTIONS"
                    placeholder="Select tax liability"
                    search-placeholder="Search liability…"
                    :disabled="isFieldReadonly"
                    :invalid="Boolean(fieldErrors.tax_liability)"
                    :error-message="fieldErrors.tax_liability?.[0] ?? ''"
                    @update:model-value="updateField('tax_liability', $event)"
                />
            </div>

            <div v-if="showGstNumberField" class="col-md-6">
                <label class="form-label" for="gst_number">GST Number</label>
                <input
                    id="gst_number"
                    :value="effectiveModel.gst_number"
                    type="text"
                    class="form-control text-uppercase"
                    maxlength="15"
                    :placeholder="GST_NUMBER_HINT"
                    :readonly="isFieldReadonly"
                    :class="{ 'is-invalid': displayFieldErrors.gst_number }"
                    @input="onGstNumberInput"
                />
                <div v-if="displayFieldErrors.gst_number" class="invalid-feedback d-block">
                    {{ displayFieldErrors.gst_number[0] }}
                </div>
            </div>

            <div v-if="showGstRateField" class="col-md-6">
                <label class="form-label" for="gst_rate">GST Rate (%)</label>
                <input
                    id="gst_rate"
                    :value="effectiveModel.gst_rate"
                    type="number"
                    min="0"
                    max="100"
                    step="0.001"
                    class="form-control"
                    placeholder="e.g. 5"
                    :readonly="isFieldReadonly"
                    :class="{ 'is-invalid': fieldErrors.gst_rate }"
                    @input="updateField('gst_rate', $event.target.value === '' ? '' : Number($event.target.value))"
                />
                <div v-if="fieldErrors.gst_rate" class="invalid-feedback d-block">
                    {{ fieldErrors.gst_rate[0] }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="gst_state_id">State</label>
                <AppSelect
                    input-id="gst_state_id"
                    :model-value="effectiveModel.state_id"
                    :options="states"
                    placeholder="Select state"
                    search-placeholder="Search state…"
                    :disabled="isFieldReadonly"
                    :invalid="Boolean(fieldErrors.state_id)"
                    :error-message="fieldErrors.state_id?.[0] ?? ''"
                    @update:model-value="updateField('state_id', $event)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import AppSelect from '../ui/AppSelect.vue';
import {
    GST_REGISTRATION_OPTIONS,
    TAX_LIABILITY_OPTIONS,
    showsGstNumber,
    showsGstRate,
    showsTaxLiability,
} from '../../composables/useGstForm';
import {
    GST_NUMBER_HINT,
    getGstNumberError,
    normalizeGstNumber,
} from '../../utils/taxIdentity';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            registration_type: '',
            tax_liability: 'forward_charge',
            gst_number: '',
            gst_rate: '',
            state_id: '',
        }),
    },
    fieldErrors: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    showInheritCheckbox: { type: Boolean, default: false },
    inheritCompanyGst: { type: Boolean, default: true },
    companyGst: { type: Object, default: () => ({}) },
    states: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'update:inheritCompanyGst']);

const localFieldErrors = reactive({});

const displayFieldErrors = computed(() => ({
    ...localFieldErrors,
    ...props.fieldErrors,
}));

const usesCompanyGst = computed(() => props.showInheritCheckbox && props.inheritCompanyGst);

const effectiveModel = computed(() => (usesCompanyGst.value ? props.companyGst : props.modelValue));

const isFieldReadonly = computed(() => props.readonly || usesCompanyGst.value);

const showTaxLiability = computed(() => showsTaxLiability(effectiveModel.value.registration_type));

const showGstNumberField = computed(() => showsGstNumber(effectiveModel.value.registration_type));

const showGstRateField = computed(() => showsGstRate(effectiveModel.value.registration_type));

function clearLocalFieldError(field) {
    delete localFieldErrors[field];
}

function onInheritChange(event) {
    emit('update:inheritCompanyGst', event.target.checked);
}

function updateField(field, value) {
    if (isFieldReadonly.value) {
        return;
    }

    clearLocalFieldError(field);

    const next = {
        ...props.modelValue,
        [field]: value,
    };

    if (field === 'registration_type') {
        if (!showsGstNumber(value)) {
            next.gst_number = '';
        }

        if (!showsGstRate(value)) {
            next.gst_rate = '';
        }

        if (!showsTaxLiability(value)) {
            next.tax_liability = null;
        } else if (!next.tax_liability) {
            next.tax_liability = 'forward_charge';
        }
    }

    emit('update:modelValue', next);
}

function onGstNumberInput(event) {
    if (isFieldReadonly.value) {
        return;
    }

    clearLocalFieldError('gst_number');

    emit('update:modelValue', {
        ...props.modelValue,
        gst_number: normalizeGstNumber(event.target.value),
    });
}

function validateGstNumber() {
    const error = getGstNumberError(effectiveModel.value.gst_number);

    if (error) {
        localFieldErrors.gst_number = [error];

        return false;
    }

    clearLocalFieldError('gst_number');

    return true;
}

defineExpose({
    validateGstNumber,
});
</script>
