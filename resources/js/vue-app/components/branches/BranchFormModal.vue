<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        size="lg"
        @close="$emit('close')"
        @submit="handleSubmit"
    >
        <template #title>
            {{ isEdit ? 'Edit Branch' : 'Register Branch' }}
        </template>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="branch_company_id">Company *</label>
                <AppSelect
                    input-id="branch_company_id"
                    v-model="form.company_id"
                    :options="companySelectOptions"
                    placeholder="Select company"
                    search-placeholder="Search company…"
                    :disabled="isEdit"
                    :invalid="Boolean(fieldErrors.company_id)"
                    :error-message="fieldErrors.company_id?.[0] ?? ''"
                />
            </div>
            <div class="col-md-6">
                <label class="form-label" for="branch_name">Branch Name *</label>
                <input
                    id="branch_name"
                    v-model="form.branch_name"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.branch_name }"
                    required
                />
                <div v-if="fieldErrors.branch_name" class="invalid-feedback d-block">
                    {{ fieldErrors.branch_name[0] }}
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="branch_contact_number">Contact Number *</label>
                <input
                    id="branch_contact_number"
                    :value="form.branch_contact_number"
                    type="tel"
                    inputmode="numeric"
                    maxlength="10"
                    autocomplete="tel"
                    class="form-control"
                    placeholder="10-digit mobile"
                    :class="{ 'is-invalid': fieldErrors.branch_contact_number || contactClientError }"
                    required
                    @keydown="allowNumericKeydown"
                    @input="onContactNumberInput"
                />
                <div v-if="contactClientError" class="invalid-feedback d-block">
                    {{ contactClientError }}
                </div>
                <div v-if="fieldErrors.branch_contact_number" class="invalid-feedback d-block">
                    {{ fieldErrors.branch_contact_number[0] }}
                </div>
                <div v-if="selectedCompanyContact" class="form-text">
                    Company contact: {{ selectedCompanyContact }}. You may reuse this number on
                    multiple branches of the same company.
                </div>
                <div v-else class="form-text">
                    Use a unique number that is not already assigned to any company or branch.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="head_office">Head Office</label>
                <AppSelect
                    input-id="head_office"
                    v-model="form.head_office"
                    :options="headOfficeOptions"
                    placeholder="Select"
                    search-placeholder="Search…"
                    :invalid="Boolean(fieldErrors.head_office)"
                    :error-message="fieldErrors.head_office?.[0] ?? ''"
                />
                <div class="form-text">Only one head office is allowed per company.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="trip_sequence_start">Trip Starting Number</label>
                <input
                    id="trip_sequence_start"
                    v-model="form.trip_sequence_start"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    class="form-control"
                    placeholder="0001"
                    :class="{ 'is-invalid': fieldErrors.trip_sequence_start || tripSequenceClientError }"
                    :disabled="isTripSequenceStartLocked"
                    @input="onTripSequenceStartInput"
                />
                <div v-if="tripSequenceClientError" class="invalid-feedback d-block">
                    {{ tripSequenceClientError }}
                </div>
                <div v-if="fieldErrors.trip_sequence_start" class="invalid-feedback d-block">
                    {{ fieldErrors.trip_sequence_start[0] }}
                </div>
                <div v-if="isTripSequenceStartLocked" class="form-text">
                    This branch already has trips. The next trip will continue from the last number.
                </div>
                <div v-else class="form-text">
                    Default is 0001. Type any number such as 500, 1000, or 10000.
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="branch_address">Address</label>
                <textarea
                    id="branch_address"
                    v-model="form.branch_address"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.branch_address }"
                    rows="3"
                />
                <div v-if="fieldErrors.branch_address" class="invalid-feedback d-block">
                    {{ fieldErrors.branch_address[0] }}
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-3">GST Settings</h6>
                    <GstDetailsSection
                        ref="gstSectionRef"
                        v-model="gstForm"
                        v-model:inherit-company-gst="inheritCompanyGst"
                        :field-errors="fieldErrors"
                        :show-inherit-checkbox="true"
                        :company-gst="companyGst"
                        :states="stateSelectOptions"
                    />
                </div>
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" :label="isEdit ? 'Update' : 'Save'" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import GstDetailsSection from '../gst/GstDetailsSection.vue';
import AppModal from '../ui/AppModal.vue';
import AppButton from '../ui/AppButton.vue';
import AppSelect from '../ui/AppSelect.vue';
import { useLocationLookups } from '../../composables/useLocationLookups';
import {
    createGstFormState,
    mapLegacyGstFields,
    normalizeGstFormForSubmit,
} from '../../composables/useGstForm';
import { allowNumericKeydown, isValidMobile, normalizeMobile } from '../../utils/phone';

const props = defineProps({
    modalId: { type: String, default: 'branchFormModal' },
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    branch: { type: Object, default: null },
    companies: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const gstSectionRef = ref(null);
const inheritCompanyGst = ref(true);
const { states, initialize: initializeLocations } = useLocationLookups();

const stateSelectOptions = computed(() =>
    states.value.map((state) => ({
        value: state.id,
        label: state.name,
    })),
);

const companySelectOptions = computed(() =>
    props.companies.map((company) => ({
        value: company.id,
        label: company.name,
    })),
);

const headOfficeOptions = [
    { value: false, label: 'No' },
    { value: true, label: 'Yes' },
];

const isEdit = computed(() => props.mode === 'edit');

const isTripSequenceStartLocked = computed(
    () => isEdit.value && Boolean(props.branch?.trip_sequence_start_locked),
);

const selectedCompany = computed(() =>
    props.companies.find((company) => company.id === form.company_id) ?? null,
);

const companyGst = computed(() => mapLegacyGstFields(selectedCompany.value ?? {}));

const selectedCompanyContact = computed(() => {
    const contact = selectedCompany.value?.contact_number;

    return contact ? normalizeMobile(contact) : '';
});

const contactClientError = ref('');
const tripSequenceClientError = ref('');

const form = reactive({
    company_id: '',
    branch_name: '',
    branch_contact_number: '',
    branch_address: '',
    head_office: false,
    trip_sequence_start: '1',
});

const gstForm = ref(createGstFormState());

function sanitizeTripSequenceStartInput(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).replace(/\D/g, '');
}

function resetForm() {
    form.company_id = props.branch?.company_id ?? '';
    form.branch_name = props.branch?.branch_name ?? '';
    form.branch_contact_number = normalizeMobile(props.branch?.branch_contact_number ?? '');
    form.branch_address = props.branch?.branch_address ?? '';
    form.head_office = Boolean(props.branch?.head_office);
    form.trip_sequence_start = props.branch
        ? sanitizeTripSequenceStartInput(props.branch.trip_sequence_start === 0 ? 1 : props.branch.trip_sequence_start)
        : '1';
    inheritCompanyGst.value = props.branch?.inherit_company_gst ?? true;
    gstForm.value = createGstFormState(props.branch ?? {});
}

watch(
    () => [props.show, props.branch, props.mode],
    async () => {
        if (props.show) {
            contactClientError.value = '';
            tripSequenceClientError.value = '';
            await initializeLocations({
                stateId: props.branch?.state_id ?? '',
            });
            resetForm();
        }
    },
    { immediate: true },
);

watch(
    () => form.company_id,
    (companyId) => {
        if (isEdit.value || !companyId) {
            return;
        }

        const companyContact = selectedCompanyContact.value;

        if (companyContact) {
            form.branch_contact_number = companyContact;
        }
    },
);

function onContactNumberInput(event) {
    contactClientError.value = '';
    form.branch_contact_number = normalizeMobile(event.target.value);
}

function onTripSequenceStartInput(event) {
    tripSequenceClientError.value = '';
    form.trip_sequence_start = sanitizeTripSequenceStartInput(event.target.value);
}

function parseTripSequenceStart(value) {
    const digits = sanitizeTripSequenceStartInput(value);

    if (digits === '') {
        return 1;
    }

    const parsed = Number.parseInt(digits, 10);

    return Number.isNaN(parsed) || parsed < 1 ? 1 : parsed;
}

function handleSubmit() {
    const contactNumber = normalizeMobile(form.branch_contact_number);

    if (!contactNumber) {
        contactClientError.value = 'Contact number is required.';

        return;
    }

    if (!isValidMobile(contactNumber)) {
        contactClientError.value = 'Please enter a valid 10-digit mobile number.';

        return;
    }

    contactClientError.value = '';

    const tripSequenceStart = parseTripSequenceStart(form.trip_sequence_start);

    if (Number.isNaN(tripSequenceStart) || tripSequenceStart < 1) {
        tripSequenceClientError.value = 'Trip starting number must be at least 1.';

        return;
    }

    tripSequenceClientError.value = '';

    if (!inheritCompanyGst.value) {
        const gstValid = gstSectionRef.value?.validateGstNumber?.() ?? true;

        if (!gstValid) {
            return;
        }
    }

    const payload = {
        company_id: form.company_id,
        branch_name: form.branch_name.trim(),
        branch_contact_number: contactNumber,
        branch_address: form.branch_address.trim() || null,
        head_office: form.head_office,
        trip_sequence_start: tripSequenceStart,
        inherit_company_gst: inheritCompanyGst.value,
    };

    if (!inheritCompanyGst.value) {
        Object.assign(payload, normalizeGstFormForSubmit(gstForm.value));
    }

    emit('submit', payload);
}
</script>
