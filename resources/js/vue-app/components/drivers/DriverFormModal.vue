<template>
    <div
        :id="modalId"
        ref="modalElement"
        class="modal fade"
        tabindex="-1"
        :aria-labelledby="`${modalId}Label`"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 :id="`${modalId}Label`" class="modal-title">
                        <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-person-plus'"></i>
                        {{ isEdit ? 'Edit Driver' : 'Add New Driver' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="close"></button>
                </div>
                <form novalidate @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_name`" class="form-label">Name *</label>
                                <input
                                    :id="`${modalId}_name`"
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
                            <div class="col-md-6">
                                <label :for="`${modalId}_mobile`" class="form-label">Mobile *</label>
                                <input
                                    :id="`${modalId}_mobile`"
                                    v-model="form.mobile"
                                    type="text"
                                    inputmode="numeric"
                                    class="form-control"
                                    :class="{ 'is-invalid': mobileError }"
                                    maxlength="10"
                                    placeholder="10-digit mobile number"
                                    required
                                    @input="onMobileInput"
                                />
                                <div v-if="mobileError" class="invalid-feedback d-block">
                                    {{ mobileError }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_license_number`" class="form-label">License Number</label>
                                <input
                                    :id="`${modalId}_license_number`"
                                    v-model="form.license_number"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.license_number }"
                                    placeholder="e.g., DL-1420110012345"
                                />
                                <small class="form-text text-muted">Optional field</small>
                                <div v-if="fieldErrors.license_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.license_number[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_address`" class="form-label">Address</label>
                                <input
                                    :id="`${modalId}_address`"
                                    v-model="form.address"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.address }"
                                    placeholder="Enter address"
                                />
                                <small class="form-text text-muted">Optional field</small>
                                <div v-if="fieldErrors.address" class="invalid-feedback d-block">
                                    {{ fieldErrors.address[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_license_expiry`" class="form-label">License Expiry</label>
                                <input
                                    :id="`${modalId}_license_expiry`"
                                    v-model="form.license_expiry_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.license_expiry_date }"
                                />
                                <div v-if="fieldErrors.license_expiry_date" class="invalid-feedback d-block">
                                    {{ fieldErrors.license_expiry_date[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_joining_date`" class="form-label">Joining Date *</label>
                                <input
                                    :id="`${modalId}_joining_date`"
                                    v-model="form.joining_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': joiningDateError }"
                                    required
                                />
                                <div v-if="joiningDateError" class="invalid-feedback d-block">
                                    {{ joiningDateError }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_aadhaar`" class="form-label">Aadhaar Number</label>
                                <input
                                    :id="`${modalId}_aadhaar`"
                                    v-model="form.aadhaar_number"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="12"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.aadhaar_number }"
                                    placeholder="12 digits"
                                    @input="onAadhaarInput"
                                />
                                <div v-if="fieldErrors.aadhaar_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.aadhaar_number[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_pan`" class="form-label">PAN Number</label>
                                <input
                                    :id="`${modalId}_pan`"
                                    v-model="form.pan_number"
                                    type="text"
                                    maxlength="10"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.pan_number }"
                                    placeholder="ABCDE1234F"
                                    @input="onPanInput"
                                />
                                <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.pan_number[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_bank_account`" class="form-label">Bank Account No.</label>
                                <input
                                    :id="`${modalId}_bank_account`"
                                    v-model="form.bank_account_number"
                                    type="text"
                                    inputmode="numeric"
                                    class="form-control"
                                    @input="onBankAccountInput"
                                />
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_bank_ifsc`" class="form-label">IFSC</label>
                                <input
                                    :id="`${modalId}_bank_ifsc`"
                                    v-model="form.bank_ifsc"
                                    type="text"
                                    class="form-control"
                                />
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_emergency_name`" class="form-label">Emergency Contact Name</label>
                                <input
                                    :id="`${modalId}_emergency_name`"
                                    v-model="form.emergency_contact_name"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.emergency_contact_name }"
                                />
                                <div v-if="fieldErrors.emergency_contact_name" class="invalid-feedback d-block">
                                    {{ fieldErrors.emergency_contact_name[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label :for="`${modalId}_emergency_phone`" class="form-label">Emergency Contact Mobile</label>
                                <input
                                    :id="`${modalId}_emergency_phone`"
                                    v-model="form.emergency_contact_phone"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="10"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.emergency_contact_phone }"
                                    placeholder="10-digit mobile number"
                                    @input="onEmergencyPhoneInput"
                                />
                                <div v-if="fieldErrors.emergency_contact_phone" class="invalid-feedback d-block">
                                    {{ fieldErrors.emergency_contact_phone[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label :for="`${modalId}_daily_wage`" class="form-label">
                                    Monthly Payment Base *
                                </label>
                                <input
                                    :id="`${modalId}_daily_wage`"
                                    v-model="form.daily_wage"
                                    v-no-scroll-change
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': monthlyPaymentError }"
                                    placeholder="e.g. 30000"
                                    required
                                />
                                <small class="form-text text-muted">
                                    Monthly payment appears in Monthly Wage Settlement.
                                </small>
                                <div v-if="monthlyPaymentError" class="invalid-feedback d-block">
                                    {{ monthlyPaymentError }}
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input
                                        :id="`${modalId}_is_active`"
                                        v-model="form.is_active"
                                        class="form-check-input"
                                        type="checkbox"
                                    />
                                    <label class="form-check-label" :for="`${modalId}_is_active`">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
                        <button type="submit" class="btn btn-success" :disabled="saving">
                            <i class="bi bi-check-circle"></i>
                            {{ saving ? (isEdit ? 'Updating...' : 'Adding...') : isEdit ? 'Update Driver' : 'Add Driver' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { normalizeDigits, normalizeMobile } from '../../utils/phone';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    driver: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    modalId: { type: String, required: true },
});

const emit = defineEmits(['close', 'submit']);

const modalElement = ref(null);
let bootstrapModal = null;

const isEdit = computed(() => props.mode === 'edit');

const mobileClientError = ref('');
const joiningDateClientError = ref('');
const monthlyPaymentClientError = ref('');

const mobileError = computed(() => {
    const serverMessage = props.fieldErrors.phone?.[0];

    return serverMessage ?? mobileClientError.value;
});

const joiningDateError = computed(() => {
    return props.fieldErrors.joining_date?.[0] ?? joiningDateClientError.value;
});

const monthlyPaymentError = computed(() => {
    return props.fieldErrors.daily_wage?.[0] ?? monthlyPaymentClientError.value;
});

const form = ref(emptyForm());

function onMobileInput(event) {
    form.value.mobile = normalizeMobile(event.target.value);
    mobileClientError.value = '';
}

function onAadhaarInput(event) {
    form.value.aadhaar_number = normalizeDigits(event.target.value, 12);
}

function onPanInput(event) {
    form.value.pan_number = String(event.target.value ?? '')
        .replace(/[^A-Za-z0-9]/g, '')
        .toUpperCase()
        .slice(0, 10);
}

function onBankAccountInput(event) {
    form.value.bank_account_number = normalizeDigits(event.target.value);
}

function onEmergencyPhoneInput(event) {
    form.value.emergency_contact_phone = normalizeMobile(event.target.value);
}

function emptyForm() {
    return {
        name: '',
        mobile: '',
        license_number: '',
        license_expiry_date: '',
        address: '',
        aadhaar_number: '',
        pan_number: '',
        bank_account_number: '',
        bank_ifsc: '',
        joining_date: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        daily_wage: '',
        is_active: true,
    };
}

function fillForm(driver) {
    mobileClientError.value = '';
    joiningDateClientError.value = '';
    monthlyPaymentClientError.value = '';

    const wage = driver?.daily_wage;

    form.value = {
        name: driver?.name ?? '',
        mobile: normalizeMobile(driver?.mobile ?? driver?.phone ?? ''),
        license_number: driver?.license_number ?? '',
        license_expiry_date: driver?.license_expiry_date ?? '',
        address: driver?.address ?? '',
        aadhaar_number: driver?.aadhaar_number ?? '',
        pan_number: driver?.pan_number ?? '',
        bank_account_number: driver?.bank_account_number ?? '',
        bank_ifsc: driver?.bank_ifsc ?? '',
        joining_date: driver?.joining_date ?? '',
        emergency_contact_name: driver?.emergency_contact_name ?? '',
        emergency_contact_phone: normalizeMobile(driver?.emergency_contact_phone ?? ''),
        daily_wage: wage !== null && wage !== undefined && wage !== '' ? String(wage) : '',
        is_active: driver?.is_active !== undefined ? Boolean(driver.is_active) : true,
    };
}

watch(
    () => props.show,
    (visible) => {
        if (!modalElement.value || typeof window.bootstrap === 'undefined') {
            return;
        }

        if (!bootstrapModal) {
            bootstrapModal = new window.bootstrap.Modal(modalElement.value, { backdrop: false });
        }

        if (visible) {
            fillForm(props.driver);
            bootstrapModal.show();
        } else {
            bootstrapModal.hide();
        }
    },
);

watch(
    () => props.driver,
    (driver) => {
        if (props.show) {
            fillForm(driver);
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    const mobile = normalizeMobile(form.value.mobile);

    if (!/^[0-9]{10}$/.test(mobile)) {
        mobileClientError.value = 'Please enter a valid 10-digit mobile number.';

        return;
    }

    mobileClientError.value = '';
    joiningDateClientError.value = '';
    monthlyPaymentClientError.value = '';

    if (!form.value.joining_date) {
        joiningDateClientError.value = 'Joining date is required.';

        return;
    }

    const wage = form.value.daily_wage === '' ? Number.NaN : Number(form.value.daily_wage);

    if (Number.isNaN(wage) || wage <= 0) {
        monthlyPaymentClientError.value = 'Monthly payment base must be greater than 0.';

        return;
    }

    emit('submit', {
        name: form.value.name.trim(),
        phone: mobile,
        license_number: form.value.license_number || null,
        license_expiry_date: form.value.license_expiry_date || null,
        address: form.value.address?.trim() || null,
        aadhaar_number: form.value.aadhaar_number || null,
        pan_number: form.value.pan_number?.toUpperCase() || null,
        bank_account_number: form.value.bank_account_number?.trim() || null,
        bank_ifsc: form.value.bank_ifsc?.trim()?.toUpperCase() || null,
        joining_date: form.value.joining_date,
        emergency_contact_name: form.value.emergency_contact_name?.trim() || null,
        emergency_contact_phone: normalizeMobile(form.value.emergency_contact_phone) || null,
        daily_wage: wage,
        is_active: Boolean(form.value.is_active),
    });
}
</script>
