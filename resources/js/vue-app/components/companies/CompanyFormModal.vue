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

        <GstDetailsSection
            ref="gstSectionRef"
            v-model="gstForm"
            :field-errors="fieldErrors"
            :states="stateSelectOptions"
        />

        <div class="row mt-1">
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
import { computed, reactive, ref, watch } from 'vue';
import GstDetailsSection from '../gst/GstDetailsSection.vue';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';
import { useLocationLookups } from '../../composables/useLocationLookups';
import {
    createGstFormState,
    normalizeGstFormForSubmit,
} from '../../composables/useGstForm';
import { allowNumericKeydown, normalizeMobile } from '../../utils/phone';
import {
    PAN_NUMBER_HINT,
    getPanNumberError,
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

const gstSectionRef = ref(null);
const localFieldErrors = reactive({});
const { states, initialize: initializeLocations } = useLocationLookups();

const stateSelectOptions = computed(() =>
    states.value.map((state) => ({
        value: state.id,
        label: state.name,
    })),
);

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

const form = reactive({
    name: '',
    pan_number: '',
    address: '',
    contact_person: '',
    contact_number: '',
    payroll_pay_days_mode: 'calendar',
});

const gstForm = ref(createGstFormState());

function resetForm() {
    form.name = '';
    form.pan_number = '';
    form.address = '';
    form.contact_person = '';
    form.contact_number = '';
    form.payroll_pay_days_mode = 'calendar';
    gstForm.value = createGstFormState();
}

function fillForm(company) {
    form.name = company?.name ?? '';
    form.pan_number = normalizePanNumber(company?.pan_number ?? '');
    form.address = company?.address ?? '';
    form.contact_person = company?.contact_person ?? '';
    form.contact_number = normalizeMobile(company?.contact_number ?? '');
    form.payroll_pay_days_mode = company?.payroll_pay_days_mode ?? 'calendar';
    gstForm.value = createGstFormState(company);
}

function submit() {
    clearLocalFieldErrors();

    const panNumber = normalizePanNumber(form.pan_number);
    const panError = getPanNumberError(panNumber, { required: true });
    const gstValid = gstSectionRef.value?.validateGstNumber?.() ?? true;

    if (panError) {
        localFieldErrors.pan_number = [panError];
    }

    if (panError || !gstValid) {
        return;
    }

    emit('submit', {
        ...form,
        ...normalizeGstFormForSubmit(gstForm.value),
        name: form.name.trim(),
        pan_number: panNumber,
        address: form.address.trim() || null,
        contact_person: form.contact_person.trim(),
        contact_number: form.contact_number.trim(),
    });
}

watch(
    () => props.show,
    async (visible) => {
        if (!visible) {
            clearLocalFieldErrors();

            return;
        }

        clearLocalFieldErrors();
        await initializeLocations({
            stateId: isEdit.value ? props.company?.state_id : '',
        });

        if (isEdit.value && props.company) {
            fillForm(props.company);

            return;
        }

        resetForm();
    },
);
</script>
