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

        <div class="row g-2">
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
            <div class="col-md-4">
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
                    Company contact: {{ selectedCompanyContact }}.
                </div>
                <div v-else class="form-text">
                    Must be unique.
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label d-block" for="head_office">Head Office</label>
                <div class="d-flex gap-3 align-items-center" style="height: 38px;">
                    <div class="form-check mb-0">
                        <input
                            id="head_office_yes"
                            v-model="form.head_office"
                            class="form-check-input"
                            type="radio"
                            :value="true"
                            name="head_office"
                        />
                        <label class="form-check-label" for="head_office_yes">Yes</label>
                    </div>
                    <div class="form-check mb-0">
                        <input
                            id="head_office_no"
                            v-model="form.head_office"
                            class="form-check-input"
                            type="radio"
                            :value="false"
                            name="head_office"
                        />
                        <label class="form-check-label" for="head_office_no">No</label>
                    </div>
                </div>
                <div class="form-text">Max 1 per company.</div>
                <div v-if="fieldErrors.head_office" class="invalid-feedback d-block mt-1">
                    {{ fieldErrors.head_office[0] }}
                </div>
            </div>
            <div class="col-md-4">
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
                    🔒 Locked (trips exist).
                </div>
                <div v-else class="form-text">
                    Default: 0001
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="branch_address">Address</label>
                <textarea
                    id="branch_address"
                    v-model="form.branch_address"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.branch_address }"
                    rows="2"
                />
                <div v-if="fieldErrors.branch_address" class="invalid-feedback d-block">
                    {{ fieldErrors.branch_address[0] }}
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-3">Logo Settings</h6>
                    <div class="form-check mb-3">
                        <input
                            id="use_company_logo"
                            v-model="useCompanyLogo"
                            class="form-check-input"
                            type="checkbox"
                        />
                        <label class="form-check-label" for="use_company_logo">
                            Use same logo of company
                        </label>
                    </div>
                    <div v-if="useCompanyLogo && selectedCompanyLogoUrl" class="mb-2">
                        <div class="form-text mb-1">Company logo preview</div>
                        <img
                            :src="selectedCompanyLogoUrl"
                            alt="Company logo"
                            class="branch-company-logo-preview"
                        />
                    </div>
                    <div v-if="!useCompanyLogo">
                        <label class="form-label" for="branch_logo">Branch Logo</label>
                        <div v-if="branchLogoPreviewUrl" class="d-flex align-items-center gap-3 mb-2">
                            <img
                                :src="branchLogoPreviewUrl"
                                alt="Branch logo preview"
                                class="branch-company-logo-preview"
                            />
                            <button
                                v-if="isEdit && existingBranchLogoUrl && !removeLogo && !logoFile"
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="onRemoveLogo"
                            >
                                Remove logo
                            </button>
                        </div>
                        <input
                            id="branch_logo"
                            ref="logoInputRef"
                            type="file"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            :class="{ 'is-invalid': fieldErrors.branch_logo }"
                            @change="onLogoFileChange"
                        />
                        <div v-if="fieldErrors.branch_logo" class="invalid-feedback d-block">
                            {{ fieldErrors.branch_logo[0] }}
                        </div>
                        <div v-else class="form-text">Optional. JPG, PNG, or WEBP up to 100 KB.</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-3">Logo Settings</h6>
                    <div class="form-check mb-3">
                        <input
                            id="use_company_logo"
                            v-model="useCompanyLogo"
                            class="form-check-input"
                            type="checkbox"
                        />
                        <label class="form-check-label" for="use_company_logo">
                            Use same logo of company
                        </label>
                    </div>
                    <div v-if="useCompanyLogo && selectedCompanyLogoUrl" class="mb-2">
                        <div class="form-text mb-1">Company logo preview</div>
                        <img
                            :src="selectedCompanyLogoUrl"
                            alt="Company logo"
                            class="branch-company-logo-preview"
                        />
                    </div>
                    <div v-if="!useCompanyLogo">
                        <label class="form-label" for="branch_logo">Branch Logo</label>
                        <div v-if="branchLogoPreviewUrl" class="d-flex align-items-center gap-3 mb-2">
                            <img
                                :src="branchLogoPreviewUrl"
                                alt="Branch logo preview"
                                class="branch-company-logo-preview"
                            />
                            <button
                                v-if="isEdit && existingBranchLogoUrl && !removeLogo && !logoFile"
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="onRemoveLogo"
                            >
                                Remove logo
                            </button>
                        </div>
                        <input
                            id="branch_logo"
                            ref="logoInputRef"
                            type="file"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            :class="{ 'is-invalid': fieldErrors.branch_logo }"
                            @change="onLogoFileChange"
                        />
                        <div v-if="fieldErrors.branch_logo" class="invalid-feedback d-block">
                            {{ fieldErrors.branch_logo[0] }}
                        </div>
                        <div v-else class="form-text">Optional. JPG, PNG, or WEBP up to 100 KB.</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-3">Logo Settings</h6>
                    <div class="form-check mb-3">
                        <input
                            id="use_company_logo"
                            v-model="useCompanyLogo"
                            class="form-check-input"
                            type="checkbox"
                        />
                        <label class="form-check-label" for="use_company_logo">
                            Use same logo of company
                        </label>
                    </div>
                    <div v-if="useCompanyLogo && selectedCompanyLogoUrl" class="mb-2">
                        <div class="form-text mb-1">Company logo preview</div>
                        <img
                            :src="selectedCompanyLogoUrl"
                            alt="Company logo"
                            class="branch-company-logo-preview"
                        />
                    </div>
                    <div v-if="!useCompanyLogo">
                        <label class="form-label" for="branch_logo">Branch Logo</label>
                        <div v-if="branchLogoPreviewUrl" class="d-flex align-items-center gap-3 mb-2">
                            <img
                                :src="branchLogoPreviewUrl"
                                alt="Branch logo preview"
                                class="branch-company-logo-preview"
                            />
                            <button
                                v-if="isEdit && existingBranchLogoUrl && !removeLogo && !logoFile"
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="onRemoveLogo"
                            >
                                Remove logo
                            </button>
                        </div>
                        <input
                            id="branch_logo"
                            ref="logoInputRef"
                            type="file"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            :class="{ 'is-invalid': fieldErrors.branch_logo }"
                            @change="onLogoFileChange"
                        />
                        <div v-if="fieldErrors.branch_logo" class="invalid-feedback d-block">
                            {{ fieldErrors.branch_logo[0] }}
                        </div>
                        <div v-else class="form-text">Optional. JPG, PNG, or WEBP up to 100 KB.</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <GstDetailsSection
                        ref="gstSectionRef"
                        v-model="gstForm"
                        v-model:inherit-company-gst="inheritCompanyGst"
                        title="GST Settings"
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
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
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
const logoInputRef = ref(null);
const inheritCompanyGst = ref(true);
const useCompanyLogo = ref(true);
const logoFile = ref(null);
const logoObjectUrl = ref('');
const removeLogo = ref(false);
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

const selectedCompanyLogoUrl = computed(() => selectedCompany.value?.logo_url ?? '');

const existingBranchLogoUrl = computed(() => {
    if (removeLogo.value || useCompanyLogo.value) {
        return '';
    }

    if (props.branch?.use_company_logo) {
        return '';
    }

    return props.branch?.logo_url ?? '';
});

const branchLogoPreviewUrl = computed(() => logoObjectUrl.value || existingBranchLogoUrl.value || '');

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

function revokeLogoObjectUrl() {
    if (logoObjectUrl.value) {
        URL.revokeObjectURL(logoObjectUrl.value);
        logoObjectUrl.value = '';
    }
}

function resetLogoState() {
    logoFile.value = null;
    removeLogo.value = false;
    revokeLogoObjectUrl();

    if (logoInputRef.value) {
        logoInputRef.value.value = '';
    }
}

function onLogoFileChange(event) {
    const file = event.target.files?.[0] ?? null;

    revokeLogoObjectUrl();
    logoFile.value = file;
    removeLogo.value = false;

    if (file) {
        logoObjectUrl.value = URL.createObjectURL(file);
    }
}

function onRemoveLogo() {
    logoFile.value = null;
    removeLogo.value = true;
    revokeLogoObjectUrl();

    if (logoInputRef.value) {
        logoInputRef.value.value = '';
    }
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
    useCompanyLogo.value = props.branch?.use_company_logo ?? true;
    gstForm.value = createGstFormState(props.branch ?? {});
    resetLogoState();
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

watch(
    () => useCompanyLogo.value,
    (enabled) => {
        if (enabled) {
            resetLogoState();
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
        use_company_logo: useCompanyLogo.value,
    };

    if (!inheritCompanyGst.value) {
        Object.assign(payload, normalizeGstFormForSubmit(gstForm.value));
    }

    if (!useCompanyLogo.value) {
        if (logoFile.value instanceof File) {
            payload.branch_logo = logoFile.value;
        }

        if (isEdit.value && removeLogo.value && !(logoFile.value instanceof File)) {
            payload.remove_logo = true;
        }
    }

    emit('submit', payload);
}

onBeforeUnmount(() => {
    revokeLogoObjectUrl();
});
</script>

<style scoped>
.branch-company-logo-preview {
    width: 88px;
    height: 88px;
    object-fit: contain;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.25rem;
}
</style>
