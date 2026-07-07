<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="$emit('close')"
        @submit="submit"
    >
        <template #title>
            {{ isEdit ? 'Edit Company' : 'Register Company' }}
        </template>
        <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label" for="company_name">Company Name *</label>
            <input
                id="company_name"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.name }"
                required
            />
            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                {{ fieldErrors.name[0] }}
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label" for="payroll_pay_days_mode">Pay Days (default)</label>
            <AppSelect
                input-id="payroll_pay_days_mode"
                v-model="form.payroll_pay_days_mode"
                :options="payrollPayDaysOptions"
                placeholder="Select pay days mode"
                search-placeholder="Search mode…"
                :invalid="Boolean(fieldErrors.payroll_pay_days_mode)"
                :error-message="fieldErrors.payroll_pay_days_mode?.[0] ?? ''"
            />
           
        </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="gst_number">GST Number</label>
                <input
                    id="gst_number"
                    :value="form.gst_number"
                    type="text"
                    class="form-control text-uppercase"
                    maxlength="15"
                    :placeholder="GST_NUMBER_HINT"
                    :class="{ 'is-invalid': displayFieldErrors.gst_number }"
                    @input="onGstNumberInput"
                />
                <div v-if="displayFieldErrors.gst_number" class="invalid-feedback d-block">
                    {{ displayFieldErrors.gst_number[0] }}
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="pan_number">PAN Number *</label>
                <input
                    id="pan_number"
                    :value="form.pan_number"
                    type="text"
                    class="form-control text-uppercase"
                    maxlength="10"
                    :placeholder="PAN_NUMBER_HINT"
                    required
                    :class="{ 'is-invalid': displayFieldErrors.pan_number }"
                    @input="onPanNumberInput"
                />
                <div v-if="displayFieldErrors.pan_number" class="invalid-feedback d-block">
                    {{ displayFieldErrors.pan_number[0] }}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="gst_type">GST Type / GST Registration Type</label>
                <AppSelect
                    input-id="gst_type"
                    v-model="form.gst_type"
                    :options="gstTypeOptions"
                    placeholder="Select"
                    search-placeholder="Search type…"
                    :invalid="Boolean(fieldErrors.gst_type)"
                    :error-message="fieldErrors.gst_type?.[0] ?? ''"
                />
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="gst_scheme">Tax Liability</label>
                <AppSelect
                    input-id="gst_scheme"
                    v-model="form.gst_scheme"
                    :options="taxLiabilityOptions"
                    placeholder="Select tax liability"
                    search-placeholder="Search liability…"
                    :invalid="Boolean(fieldErrors.gst_scheme)"
                    :error-message="fieldErrors.gst_scheme?.[0] ?? ''"
                />
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="address">Address</label>
            <textarea id="address" v-model="form.address" class="form-control" rows="2" />
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="contact_person">Contact Person *</label>
                <input
                    id="contact_person"
                    v-model="form.contact_person"
                    type="text"
                    class="form-control"
                    required
                    :class="{ 'is-invalid': fieldErrors.contact_person }"
                />
                <div v-if="fieldErrors.contact_person" class="invalid-feedback d-block">
                    {{ fieldErrors.contact_person[0] }}
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="contact_number">Contact Number *</label>
                <input
                    id="contact_number"
                    :value="form.contact_number"
                    type="tel"
                    inputmode="numeric"
                    maxlength="10"
                    autocomplete="tel"
                    class="form-control"
                    placeholder="10-digit mobile"
                    required
                    :class="{ 'is-invalid': fieldErrors.contact_number }"
                    @keydown="allowNumericKeydown"
                    @input="onContactNumberInput"
                />
                <div v-if="fieldErrors.contact_number" class="invalid-feedback d-block">
                    {{ fieldErrors.contact_number[0] }}
                </div>
            </div>
        </div>
        

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn btn-brand-primary" :disabled="saving">
                {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Create' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';
import { allowNumericKeydown, normalizeMobile } from '../../utils/phone';
import {
    GST_NUMBER_HINT,
    PAN_NUMBER_HINT,
    getGstNumberError,
    getPanNumberError,
    normalizeGstNumber,
    normalizePanNumber,
} from '../../utils/taxIdentity';

const props = defineProps({
    modalId: { type: String, default: 'companyFormModal' },
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    company: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const localFieldErrors = reactive({});

const displayFieldErrors = computed(() => ({
    ...localFieldErrors,
    ...props.fieldErrors,
}));

function clearLocalFieldError(field) {
    delete localFieldErrors[field];
}

function clearLocalFieldErrors() {
    Object.keys(localFieldErrors).forEach((field) => {
        delete localFieldErrors[field];
    });
}

function onGstNumberInput(event) {
    form.gst_number = normalizeGstNumber(event.target.value);
    clearLocalFieldError('gst_number');
}

function onPanNumberInput(event) {
    form.pan_number = normalizePanNumber(event.target.value);
    clearLocalFieldError('pan_number');
}

function onContactNumberInput(event) {
    form.contact_number = normalizeMobile(event.target.value);
}

const isEdit = computed(() => props.mode === 'edit');

const payrollPayDaysOptions = [
    { value: 'calendar', label: 'Calendar days (default)' },
    { value: 'fixed_30', label: 'Fixed 30 days' },
];

const taxLiabilityOptions = [
    { value: 'forward charge', label: 'Forward Charge' },
    { value: 'reverse charge', label: 'Reverse Charge' },
];

const gstTypeOptions = [
    { value: 'regular', label: 'Regular' },
    { value: 'composition', label: 'Composition' },
    { value: 'unregistered', label: 'Unregistered' },
];

const form = reactive({
    name: '',
    gst_number: '',
    pan_number: '',
    gst_scheme: 'forward charge',
    gst_type: '',
    address: '',
    contact_person: '',
    contact_number: '',
    payroll_pay_days_mode: 'calendar',
});

function resetForm() {
    form.name = '';
    form.gst_number = '';
    form.pan_number = '';
    form.gst_scheme = 'forward charge';
    form.gst_type = '';
    form.address = '';
    form.contact_person = '';
    form.contact_number = '';
    form.payroll_pay_days_mode = 'calendar';
}

function fillForm(company) {
    form.name = company?.name ?? '';
    form.gst_number = normalizeGstNumber(company?.gst_number ?? '');
    form.pan_number = normalizePanNumber(company?.pan_number ?? '');
    form.gst_scheme = company?.gst_scheme ?? 'forward charge';
    form.gst_type = company?.gst_type ?? '';
    form.address = company?.address ?? '';
    form.contact_person = company?.contact_person ?? '';
    form.contact_number = normalizeMobile(company?.contact_number ?? '');
    form.payroll_pay_days_mode = company?.payroll_pay_days_mode ?? 'calendar';
}

function submit() {
    clearLocalFieldErrors();

    const gstNumber = normalizeGstNumber(form.gst_number);
    const panNumber = normalizePanNumber(form.pan_number);
    const gstError = getGstNumberError(gstNumber);
    const panError = getPanNumberError(panNumber, { required: true });

    if (gstError) {
        localFieldErrors.gst_number = [gstError];
    }

    if (panError) {
        localFieldErrors.pan_number = [panError];
    }

    if (gstError || panError) {
        return;
    }

    emit('submit', {
        ...form,
        name: form.name.trim(),
        gst_number: gstNumber || null,
        pan_number: panNumber,
        gst_scheme: form.gst_scheme || null,
        gst_type: form.gst_type || null,
        address: form.address.trim() || null,
        contact_person: form.contact_person.trim(),
        contact_number: form.contact_number.trim(),
    });
}

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            clearLocalFieldErrors();

            return;
        }

        clearLocalFieldErrors();

        if (isEdit.value && props.company) {
            fillForm(props.company);
            return;
        }

        resetForm();
    },
);
</script>
