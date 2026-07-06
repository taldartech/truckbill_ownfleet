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

        <div class="mb-3">
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
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="gst_number">GST Number</label>
                <input
                    id="gst_number"
                    v-model="form.gst_number"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.gst_number }"
                />
                <div v-if="fieldErrors.gst_number" class="invalid-feedback d-block">
                    {{ fieldErrors.gst_number[0] }}
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="pan_number">PAN Number *</label>
                <input
                    id="pan_number"
                    v-model="form.pan_number"
                    type="text"
                    class="form-control"
                    required
                    :class="{ 'is-invalid': fieldErrors.pan_number }"
                />
                <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                    {{ fieldErrors.pan_number[0] }}
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="gst_scheme">GST Scheme</label>
                <input id="gst_scheme" v-model="form.gst_scheme" type="text" class="form-control" />
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="gst_type">GST Type</label>
                <input id="gst_type" v-model="form.gst_type" type="text" class="form-control" />
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="address">Address</label>
            <textarea id="address" v-model="form.address" class="form-control" rows="2" />
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="contact_person">Contact Person</label>
                <input
                    id="contact_person"
                    v-model="form.contact_person"
                    type="text"
                    class="form-control"
                />
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
        <div class="mb-0">
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
            <small class="form-text text-muted">
                Used for monthly wage settlement and per-day salary calculations.
            </small>
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

function onContactNumberInput(event) {
    form.contact_number = normalizeMobile(event.target.value);
}

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

const isEdit = computed(() => props.mode === 'edit');

const payrollPayDaysOptions = [
    { value: 'calendar', label: 'Calendar days (default)' },
    { value: 'fixed_30', label: 'Fixed 30 days' },
];

const form = reactive({
    name: '',
    gst_number: '',
    pan_number: '',
    gst_scheme: '',
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
    form.gst_scheme = '';
    form.gst_type = '';
    form.address = '';
    form.contact_person = '';
    form.contact_number = '';
    form.payroll_pay_days_mode = 'calendar';
}

function fillForm(company) {
    form.name = company?.name ?? '';
    form.gst_number = company?.gst_number ?? '';
    form.pan_number = company?.pan_number ?? '';
    form.gst_scheme = company?.gst_scheme ?? '';
    form.gst_type = company?.gst_type ?? '';
    form.address = company?.address ?? '';
    form.contact_person = company?.contact_person ?? '';
    form.contact_number = normalizeMobile(company?.contact_number ?? '');
    form.payroll_pay_days_mode = company?.payroll_pay_days_mode ?? 'calendar';
}

function submit() {
    emit('submit', form);
}

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            return;
        }

        if (isEdit.value && props.company) {
            fillForm(props.company);
            return;
        }

        resetForm();
    },
);
</script>
