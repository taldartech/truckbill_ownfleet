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
            <div class="mb-3" :class="showPayDaysField ? 'col-md-6' : 'col-12'">
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
            <div v-if="showPayDaysField" class="col-md-6 mb-3">
                <label class="form-label" for="payroll_pay_days_mode">Pay Days (Default)</label>
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
        >
            <template #after-state>
                <div class="col-md-6">
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
            </template>

            <template #after-grid>
                <div class="col-12">
                    <label class="form-label" for="address">Address</label>
                    <textarea id="address" v-model="form.address" class="form-control" rows="2" />
                </div>
            </template>
        </GstDetailsSection>
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

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="company_logo">Company Logo</label>
                <div v-if="logoPreviewUrl" class="d-flex align-items-center gap-3 mb-2">
                    <img :src="logoPreviewUrl" alt="Logo preview" class="company-logo-preview" />
                    <button
                        v-if="isEdit && existingLogoUrl && !removeLogo && !logoFile"
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        @click="onRemoveLogo"
                    >
                        Remove logo
                    </button>
                </div>
                <input
                    id="company_logo"
                    ref="logoInputRef"
                    type="file"
                    class="form-control"
                    accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                    :class="{ 'is-invalid': fieldErrors.company_logo }"
                    @change="onLogoFileChange"
                />
                <div v-if="fieldErrors.company_logo" class="invalid-feedback d-block">
                    {{ fieldErrors.company_logo[0] }}
                </div>
                <div v-else class="form-text">Optional. JPG, PNG, or WEBP up to 100 KB.</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="document_color">Document Color</label>
                <div class="d-flex align-items-center gap-2">
                    <input
                        id="document_color"
                        type="color"
                        class="form-control form-control-color document-color-input"
                        :value="form.document_color || '#0B2A55'"
                        :class="{ 'is-invalid': fieldErrors.document_color }"
                        title="Choose document color"
                        @input="onDocumentColorPickerInput"
                    />
                    <input
                        id="document_color_hex"
                        v-model="form.document_color"
                        type="text"
                        class="form-control text-uppercase"
                        maxlength="7"
                        placeholder="Optional"
                        :class="{ 'is-invalid': fieldErrors.document_color }"
                        @input="onDocumentColorInput"
                    />
                    <button
                        v-if="form.document_color"
                        type="button"
                        class="btn btn-outline-secondary"
                        title="Clear color"
                        @click="form.document_color = ''"
                    >
                        Clear
                    </button>
                </div>
                <div v-if="fieldErrors.document_color" class="invalid-feedback d-block">
                    {{ fieldErrors.document_color[0] }}
                </div>
                <div v-else class="form-text">
                    Optional. Accent color for bilty and invoice PDFs.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="digital_signature">Digital Signature</label>
                <div v-if="signaturePreviewUrl" class="d-flex align-items-center gap-3 mb-2">
                    <img :src="signaturePreviewUrl" alt="Signature preview" class="company-signature-preview" />
                    <button
                        v-if="isEdit && existingSignatureUrl && !removeSignature && !signatureFile"
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        @click="onRemoveSignature"
                    >
                        Remove signature
                    </button>
                </div>
                <input
                    id="digital_signature"
                    ref="signatureInputRef"
                    type="file"
                    class="form-control"
                    accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                    :class="{ 'is-invalid': fieldErrors.digital_signature }"
                    @change="onSignatureFileChange"
                />
                <div v-if="fieldErrors.digital_signature" class="invalid-feedback d-block">
                    {{ fieldErrors.digital_signature[0] }}
                </div>
                <div v-else class="form-text">
                    Optional. Printed as the authorized signature on bilty and invoice PDFs.
                    PNG with a transparent background works best (JPG also accepted), up to 75 KB.
                </div>
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn" :class="isEdit ? 'btn-warning' : 'btn-success'" :disabled="saving">
                {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Create' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
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
const logoInputRef = ref(null);
const logoFile = ref(null);
const logoObjectUrl = ref('');
const removeLogo = ref(false);
const signatureInputRef = ref(null);
const signatureFile = ref(null);
const signatureObjectUrl = ref('');
const removeSignature = ref(false);
const localFieldErrors = reactive({});
const { states, initialize: initializeLocations } = useLocationLookups();

const existingLogoUrl = computed(() => {
    if (removeLogo.value) {
        return '';
    }

    return props.company?.logo_url ?? '';
});

const logoPreviewUrl = computed(() => logoObjectUrl.value || existingLogoUrl.value || '');

const existingSignatureUrl = computed(() => {
    if (removeSignature.value) {
        return '';
    }

    return props.company?.signature_url ?? '';
});

const signaturePreviewUrl = computed(() => signatureObjectUrl.value || existingSignatureUrl.value || '');

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

const showPayDaysField = false;

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
    document_color: '',
});

const gstForm = ref(createGstFormState());

function normalizeDocumentColor(value) {
    const color = String(value ?? '')
        .trim()
        .toUpperCase();

    if (! color) {
        return null;
    }

    if (/^#[0-9A-F]{6}$/.test(color)) {
        return color;
    }

    if (/^[0-9A-F]{6}$/.test(color)) {
        return `#${color}`;
    }

    return color;
}

function onDocumentColorInput(event) {
    const raw = String(event.target.value ?? '')
        .trim()
        .toUpperCase();

    if (! raw || raw === '#') {
        form.document_color = '';

        return;
    }

    form.document_color = raw.startsWith('#') ? raw.slice(0, 7) : `#${raw.slice(0, 6)}`;
}

function onDocumentColorPickerInput(event) {
    form.document_color = String(event.target.value ?? '')
        .trim()
        .toUpperCase();
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

function revokeSignatureObjectUrl() {
    if (signatureObjectUrl.value) {
        URL.revokeObjectURL(signatureObjectUrl.value);
        signatureObjectUrl.value = '';
    }
}

function resetSignatureState() {
    signatureFile.value = null;
    removeSignature.value = false;
    revokeSignatureObjectUrl();

    if (signatureInputRef.value) {
        signatureInputRef.value.value = '';
    }
}

function onSignatureFileChange(event) {
    const file = event.target.files?.[0] ?? null;

    revokeSignatureObjectUrl();
    signatureFile.value = file;
    removeSignature.value = false;

    if (file) {
        signatureObjectUrl.value = URL.createObjectURL(file);
    }
}

function onRemoveSignature() {
    signatureFile.value = null;
    removeSignature.value = true;
    revokeSignatureObjectUrl();

    if (signatureInputRef.value) {
        signatureInputRef.value.value = '';
    }
}

function resetForm() {
    form.name = '';
    form.pan_number = '';
    form.address = '';
    form.contact_person = '';
    form.contact_number = '';
    form.payroll_pay_days_mode = 'calendar';
    form.document_color = '';
    gstForm.value = createGstFormState();
    resetLogoState();
    resetSignatureState();
}

function fillForm(company) {
    form.name = company?.name ?? '';
    form.pan_number = normalizePanNumber(company?.pan_number ?? '');
    form.address = company?.address ?? '';
    form.contact_person = company?.contact_person ?? '';
    form.contact_number = normalizeMobile(company?.contact_number ?? '');
    form.payroll_pay_days_mode = company?.payroll_pay_days_mode ?? 'calendar';
    form.document_color = normalizeDocumentColor(company?.document_color) ?? '';
    gstForm.value = createGstFormState(company);
    resetLogoState();
    resetSignatureState();
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

    const payload = {
        ...form,
        ...normalizeGstFormForSubmit(gstForm.value),
        name: form.name.trim(),
        pan_number: panNumber,
        address: form.address.trim() || null,
        contact_person: form.contact_person.trim(),
        contact_number: form.contact_number.trim(),
        document_color: normalizeDocumentColor(form.document_color),
    };

    if (logoFile.value instanceof File) {
        payload.company_logo = logoFile.value;
    }

    if (isEdit.value && removeLogo.value && !(logoFile.value instanceof File)) {
        payload.remove_logo = true;
    }

    if (signatureFile.value instanceof File) {
        payload.digital_signature = signatureFile.value;
    }

    if (isEdit.value && removeSignature.value && !(signatureFile.value instanceof File)) {
        payload.remove_signature = true;
    }

    emit('submit', payload);
}

watch(
    () => props.show,
    async (visible) => {
        if (!visible) {
            clearLocalFieldErrors();
            resetLogoState();
            resetSignatureState();

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

onBeforeUnmount(() => {
    revokeLogoObjectUrl();
    revokeSignatureObjectUrl();
});
</script>

<style scoped>
.company-logo-preview {
    width: 88px;
    height: 88px;
    object-fit: contain;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.25rem;
}

.company-signature-preview {
    width: 160px;
    height: 64px;
    object-fit: contain;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.25rem;
}

.document-color-input {
    width: 3rem;
    height: 2.5rem;
    padding: 0.2rem;
}
</style>
