<template>
    <div class="gst-details-section">
        <div v-if="title || showInheritCheckbox" class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h6 v-if="title" class="mb-0">{{ title }}</h6>
            <div v-if="showInheritCheckbox" class="form-check mb-0">
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
        </div>

        <div v-if="usesCompanyGst" class="alert alert-info border-0 bg-light py-2 px-3 mb-0" style="border-left: 3px solid #2fb36f !important;">
            <div class="fw-bold text-success mb-1 small">✓ Using Company GST</div>
            <div class="text-secondary small fw-medium">
                {{ companyGstSummary }}
            </div>
        </div>

        <div v-else class="row g-2">
            <div class="col-md-6">
                <label class="form-label" for="gst_registration_type">GST Registration Type</label>
                <AppSelect
                    input-id="gst_registration_type"
                    :model-value="effectiveModel.registration_type"
                    :options="GST_REGISTRATION_OPTIONS"
                    placeholder="Select registration type"
                    search-placeholder="Search type…"
                    :disabled="isFieldReadonly"
                    :invalid="Boolean(displayFieldErrors.registration_type)"
                    :error-message="displayFieldErrors.registration_type?.[0] ?? ''"
                    @update:model-value="updateField('registration_type', $event)"
                />
            </div>

            <div v-if="showTaxLiability" class="col-md-6">
                <label class="form-label" for="gst_tax_liability">Tax Liability *</label>
                <AppSelect
                    input-id="gst_tax_liability"
                    :model-value="effectiveModel.tax_liability"
                    :options="TAX_LIABILITY_OPTIONS"
                    placeholder="Select tax liability"
                    search-placeholder="Search liability…"
                    :disabled="isFieldReadonly"
                    :invalid="Boolean(displayFieldErrors.tax_liability)"
                    :error-message="displayFieldErrors.tax_liability?.[0] ?? ''"
                    @update:model-value="updateField('tax_liability', $event)"
                />
            </div>

            <div v-if="showGstNumberField" class="col-md-6">
                <label class="form-label" for="gst_number">GST Number *</label>
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
                <label class="form-label" for="gst_rate">GST Rate (%) *</label>
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
                    :class="{ 'is-invalid': displayFieldErrors.gst_rate }"
                    @input="updateField('gst_rate', $event.target.value === '' ? '' : Number($event.target.value))"
                />
                <div v-if="displayFieldErrors.gst_rate" class="invalid-feedback d-block">
                    {{ displayFieldErrors.gst_rate[0] }}
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
                    :invalid="Boolean(displayFieldErrors.state_id)"
                    :error-message="displayFieldErrors.state_id?.[0] ?? ''"
                    @update:model-value="updateField('state_id', $event)"
                />
            </div>

            <slot name="after-state" />
            <slot name="after-grid" />
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import AppSelect from '../ui/AppSelect.vue';
import {
    GST_REGISTRATION_OPTIONS,
    TAX_LIABILITY_OPTIONS,
    showsGstNumber,
    showsGstRate,
    showsTaxLiability,
    registrationTypeLabel,
    taxLiabilityLabel,
} from '../../composables/useGstForm';
import {
    GST_NUMBER_HINT,
    getGstNumberError,
    normalizeGstNumber,
} from '../../utils/taxIdentity';

const props = defineProps({
    title: { type: String, default: '' },
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

const displayFieldErrors = computed(() => {
    const errors = {
        ...localFieldErrors,
        ...props.fieldErrors,
    };
    const registrationType = effectiveModel.value.registration_type;

    if (!showsGstNumber(registrationType)) {
        delete errors.gst_number;
    }
    if (!showsTaxLiability(registrationType)) {
        delete errors.tax_liability;
    }
    if (!showsGstRate(registrationType)) {
        delete errors.gst_rate;
    }

    return errors;
});

const usesCompanyGst = computed(() => props.showInheritCheckbox && props.inheritCompanyGst);

const companyGstSummary = computed(() => {
    const g = props.companyGst;
    if (!g) return 'No company GST settings configured.';

    const stateObj = props.states.find(s => String(s.value) === String(g.state_id));
    const stateLabel = stateObj ? stateObj.label : '';

    const parts = [];

    if (g.registration_type) {
        parts.push(registrationTypeLabel(g.registration_type));
    }
    if (g.gst_number) {
        parts.push(g.gst_number);
    }
    if (showsTaxLiability(g.registration_type) && g.tax_liability) {
        parts.push(taxLiabilityLabel(g.tax_liability));
    }
    if (showsGstRate(g.registration_type) && g.gst_rate !== '' && g.gst_rate !== null && g.gst_rate !== undefined) {
        parts.push(`${g.gst_rate}%`);
    }
    if (stateLabel) {
        parts.push(stateLabel);
    }

    if (parts.length === 0) {
        return 'No GST details configured for company.';
    }

    return parts.join(' | ');
});

const effectiveModel = computed(() => (usesCompanyGst.value ? props.companyGst : props.modelValue));

const isFieldReadonly = computed(() => props.readonly || usesCompanyGst.value);

const showTaxLiability = computed(() => showsTaxLiability(effectiveModel.value.registration_type));

const showGstNumberField = computed(() => showsGstNumber(effectiveModel.value.registration_type));

const showGstRateField = computed(() => showsGstRate(effectiveModel.value.registration_type));

watch(
    () => effectiveModel.value.registration_type,
    (newType) => {
        if (!showsGstNumber(newType)) {
            delete localFieldErrors.gst_number;
        }
        if (!showsTaxLiability(newType)) {
            delete localFieldErrors.tax_liability;
        }
        if (!showsGstRate(newType)) {
            delete localFieldErrors.gst_rate;
        }
    },
);

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
    let isValid = true;

    // Reset local errors
    delete localFieldErrors.gst_number;
    delete localFieldErrors.tax_liability;
    delete localFieldErrors.gst_rate;

    const registrationType = effectiveModel.value.registration_type;

    // 1. GST Number validation
    if (showsGstNumber(registrationType)) {
        const gstNum = effectiveModel.value.gst_number;
        if (!gstNum) {
            localFieldErrors.gst_number = ['GST number is required.'];
            isValid = false;
        } else {
            const error = getGstNumberError(gstNum);
            if (error) {
                localFieldErrors.gst_number = [error];
                isValid = false;
            }
        }
    }

    // 2. Tax Liability validation
    if (showsTaxLiability(registrationType)) {
        const taxLiab = effectiveModel.value.tax_liability;
        if (!taxLiab) {
            localFieldErrors.tax_liability = ['Tax liability is required.'];
            isValid = false;
        }
    }

    // 3. GST Rate validation
    if (showsGstRate(registrationType)) {
        const rate = effectiveModel.value.gst_rate;
        if (rate === null || rate === undefined || rate === '') {
            localFieldErrors.gst_rate = ['GST rate is required.'];
            isValid = false;
        } else {
            const numRate = Number(rate);
            if (isNaN(numRate) || numRate < 0 || numRate > 100) {
                localFieldErrors.gst_rate = ['GST rate must be between 0 and 100.'];
                isValid = false;
            }
        }
    }

    return isValid;
}

defineExpose({
    validateGstNumber,
});
</script>
