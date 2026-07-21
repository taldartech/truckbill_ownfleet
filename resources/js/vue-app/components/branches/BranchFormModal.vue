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
                    <h6 class="mb-3">Trip Number Format</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="trip_initial">Initial *</label>
                            <input
                                id="trip_initial"
                                v-model="numberSettings.trip_initial"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': fieldErrors['number_settings.trip_initial'] }"
                                maxlength="50"
                            />
                            <div v-if="fieldErrors['number_settings.trip_initial']" class="invalid-feedback d-block">
                                {{ fieldErrors['number_settings.trip_initial'][0] }}
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="trip_separator_1">Separator</label>
                            <input
                                id="trip_separator_1"
                                v-model="numberSettings.trip_separator_1"
                                type="text"
                                class="form-control"
                                maxlength="5"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="trip_year_type">Year</label>
                            <AppSelect
                                input-id="trip_year_type"
                                v-model="numberSettings.trip_year_type"
                                :options="yearTypeOptions"
                                placeholder="Select"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="trip_year_digits">Year Digits</label>
                            <AppSelect
                                input-id="trip_year_digits"
                                v-model="numberSettings.trip_year_digits"
                                :options="yearDigitsOptions"
                                :disabled="numberSettings.trip_year_type === 'blank'"
                                placeholder="Select"
                            />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="trip_separator_2">Separator</label>
                            <input
                                id="trip_separator_2"
                                v-model="numberSettings.trip_separator_2"
                                type="text"
                                class="form-control"
                                maxlength="5"
                                :disabled="numberSettings.trip_year_type === 'blank'"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="trip_sequence_digits">Sequence Digits *</label>
                            <input
                                id="trip_sequence_digits"
                                v-model.number="numberSettings.trip_sequence_digits"
                                type="number"
                                min="1"
                                max="12"
                                class="form-control"
                                :class="{ 'is-invalid': fieldErrors['number_settings.trip_sequence_digits'] }"
                            />
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                Example: <strong>{{ tripNumberExample }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-3">Invoice Number Format</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="invoice_initial">Initial *</label>
                            <input
                                id="invoice_initial"
                                v-model="numberSettings.invoice_initial"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': fieldErrors['number_settings.invoice_initial'] }"
                                maxlength="50"
                            />
                            <div v-if="fieldErrors['number_settings.invoice_initial']" class="invalid-feedback d-block">
                                {{ fieldErrors['number_settings.invoice_initial'][0] }}
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="invoice_separator_1">Separator</label>
                            <input
                                id="invoice_separator_1"
                                v-model="numberSettings.invoice_separator_1"
                                type="text"
                                class="form-control"
                                maxlength="5"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="invoice_year_type">Year</label>
                            <AppSelect
                                input-id="invoice_year_type"
                                v-model="numberSettings.invoice_year_type"
                                :options="yearTypeOptions"
                                placeholder="Select"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="invoice_year_digits">Year Digits</label>
                            <AppSelect
                                input-id="invoice_year_digits"
                                v-model="numberSettings.invoice_year_digits"
                                :options="yearDigitsOptions"
                                :disabled="numberSettings.invoice_year_type === 'blank'"
                                placeholder="Select"
                            />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="invoice_separator_2">Separator</label>
                            <input
                                id="invoice_separator_2"
                                v-model="numberSettings.invoice_separator_2"
                                type="text"
                                class="form-control"
                                maxlength="5"
                                :disabled="numberSettings.invoice_year_type === 'blank'"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="invoice_sequence_digits">Sequence Digits *</label>
                            <input
                                id="invoice_sequence_digits"
                                v-model.number="numberSettings.invoice_sequence_digits"
                                type="number"
                                min="1"
                                max="12"
                                class="form-control"
                                :class="{ 'is-invalid': fieldErrors['number_settings.invoice_sequence_digits'] }"
                            />
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                Example: <strong>{{ invoiceNumberExample }}</strong>
                            </div>
                        </div>
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
            <AppButton
                type="submit"
                :variant="isEdit ? 'warning' : 'success'"
                :label="isEdit ? 'Update' : 'Save'"
                :loading="saving"
            />
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

const yearTypeOptions = [
    { value: 'blank', label: 'Blank (no year)' },
    { value: 'year', label: 'Calendar year' },
    { value: 'financial_year', label: 'Financial year (Apr–Mar)' },
];

const yearDigitsOptions = [
    { value: 2, label: '2 digits' },
    { value: 4, label: '4 digits' },
];

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

const form = reactive({
    company_id: '',
    branch_name: '',
    branch_contact_number: '',
    branch_address: '',
    head_office: false,
});

const numberSettings = reactive(defaultNumberSettings());

const gstForm = ref(createGstFormState());

const tripNumberExample = computed(() => buildNumberExample(numberSettings, 'trip'));
const invoiceNumberExample = computed(() => buildNumberExample(numberSettings, 'invoice'));

function defaultNumberSettings(branch = null) {
    const branchCode = branch?.id ? `BR${String(branch.id).padStart(3, '0')}` : 'BR000';
    const settings = branch?.number_settings ?? {};

    return {
        trip_initial: settings.trip_initial ?? `TRIP-${branchCode}`,
        trip_separator_1: settings.trip_separator_1 ?? '-',
        trip_year_type: settings.trip_year_type ?? 'year',
        trip_year_digits: Number(settings.trip_year_digits ?? 4),
        trip_separator_2: settings.trip_separator_2 ?? '-',
        trip_sequence_digits: Number(settings.trip_sequence_digits ?? 4),
        invoice_initial: settings.invoice_initial ?? `INV-${branchCode}`,
        invoice_separator_1: settings.invoice_separator_1 ?? '-',
        invoice_year_type: settings.invoice_year_type ?? 'year',
        invoice_year_digits: Number(settings.invoice_year_digits ?? 4),
        invoice_separator_2: settings.invoice_separator_2 ?? '-',
        invoice_sequence_digits: Number(settings.invoice_sequence_digits ?? 4),
    };
}

function buildNumberExample(settings, kind) {
    const initial = String(settings[`${kind}_initial`] ?? '').trim() || (kind === 'invoice' ? 'INV' : 'TRIP');
    const separator1 = String(settings[`${kind}_separator_1`] ?? '');
    const yearType = settings[`${kind}_year_type`] ?? 'year';
    const yearDigits = Number(settings[`${kind}_year_digits`] ?? 4) === 2 ? 2 : 4;
    const separator2 = String(settings[`${kind}_separator_2`] ?? '');
    const sequenceDigits = Math.max(1, Number(settings[`${kind}_sequence_digits`] ?? 4) || 4);
    const sequence = String(1).padStart(sequenceDigits, '0');
    const now = new Date();
    let datePart = '';

    if (yearType === 'year') {
        datePart = yearDigits === 2
            ? String(now.getFullYear()).slice(-2)
            : String(now.getFullYear());
    } else if (yearType === 'financial_year') {
        const startYear = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1;
        const endYear = startYear + 1;
        datePart = yearDigits === 2
            ? `${String(startYear).slice(-2)}-${String(endYear).slice(-2)}`
            : `${startYear}-${String(endYear).slice(-2)}`;
    }

    if (!datePart) {
        return `${initial}${separator1}${sequence}`;
    }

    return `${initial}${separator1}${datePart}${separator2}${sequence}`;
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
    Object.assign(numberSettings, defaultNumberSettings(props.branch));
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

    if (!String(numberSettings.trip_initial ?? '').trim() || !String(numberSettings.invoice_initial ?? '').trim()) {
        return;
    }

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
        number_settings: {
            trip_initial: String(numberSettings.trip_initial).trim(),
            trip_separator_1: String(numberSettings.trip_separator_1 ?? ''),
            trip_year_type: numberSettings.trip_year_type,
            trip_year_digits: Number(numberSettings.trip_year_digits),
            trip_separator_2: String(numberSettings.trip_separator_2 ?? ''),
            trip_sequence_digits: Number(numberSettings.trip_sequence_digits),
            invoice_initial: String(numberSettings.invoice_initial).trim(),
            invoice_separator_1: String(numberSettings.invoice_separator_1 ?? ''),
            invoice_year_type: numberSettings.invoice_year_type,
            invoice_year_digits: Number(numberSettings.invoice_year_digits),
            invoice_separator_2: String(numberSettings.invoice_separator_2 ?? ''),
            invoice_sequence_digits: Number(numberSettings.invoice_sequence_digits),
        },
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
