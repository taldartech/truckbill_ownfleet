<template>
    <div
        :id="modalId"
        class="modal fade"
        tabindex="-1"
        :class="{ show: show }"
        :style="show ? { display: 'block' } : {}"
        :aria-hidden="!show"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form @submit.prevent="handleSubmit">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ isEdit ? 'Edit Branch' : 'Register Branch' }}</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')" />
                    </div>
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="branch_company_id">Company *</label>
                                <select
                                    id="branch_company_id"
                                    v-model="form.company_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldErrors.company_id }"
                                    :disabled="isEdit"
                                    required
                                >
                                    <option value="">Select company</option>
                                    <option v-for="company in companies" :key="company.id" :value="company.id">
                                        {{ company.name }}
                                    </option>
                                </select>
                                <div v-if="fieldErrors.company_id" class="invalid-feedback d-block">
                                    {{ fieldErrors.company_id[0] }}
                                </div>
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
                                <select
                                    id="head_office"
                                    v-model="form.head_office"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldErrors.head_office }"
                                >
                                    <option :value="false">No</option>
                                    <option :value="true">Yes</option>
                                </select>
                                <div v-if="fieldErrors.head_office" class="invalid-feedback d-block">
                                    {{ fieldErrors.head_office[0] }}
                                </div>
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
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                        <AppButton type="submit" :label="isEdit ? 'Update' : 'Save'" :loading="saving" />
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppButton from '../ui/AppButton.vue';
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

const isEdit = computed(() => props.mode === 'edit');

const selectedCompany = computed(() =>
    props.companies.find((company) => company.id === form.company_id) ?? null,
);

const selectedCompanyContact = computed(() => {
    const contact = selectedCompany.value?.contact_number;

    return contact ? normalizeMobile(contact) : '';
});

const contactClientError = ref('');

const form = reactive({
    company_id: '',
    branch_name: '',
    branch_contact_number: '',
    branch_address: '',
    head_office: false,
});

function resetForm() {
    form.company_id = props.branch?.company_id ?? '';
    form.branch_name = props.branch?.branch_name ?? '';
    form.branch_contact_number = normalizeMobile(props.branch?.branch_contact_number ?? '');
    form.branch_address = props.branch?.branch_address ?? '';
    form.head_office = Boolean(props.branch?.head_office);
}

watch(
    () => [props.show, props.branch, props.mode],
    () => {
        if (props.show) {
            contactClientError.value = '';
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

    emit('submit', {
        company_id: form.company_id,
        branch_name: form.branch_name.trim(),
        branch_contact_number: contactNumber,
        branch_address: form.branch_address.trim() || null,
        head_office: form.head_office,
    });
}
</script>
