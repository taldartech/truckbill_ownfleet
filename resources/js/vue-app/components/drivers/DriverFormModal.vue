<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-person-plus'"></i>
            {{ isEdit ? 'Edit Driver' : 'Add New Driver' }}
        </template>

        <div class="driver-form-grid">
            <div>
                <label :for="`${modalId}_name`" class="form-label">Name *</label>
                <input
                    :id="`${modalId}_name`"
                    v-model="form.name"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.name }"
                    required
                />
                <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                    {{ fieldErrors.name[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_mobile`" class="form-label">Mobile *</label>
                <input
                    :id="`${modalId}_mobile`"
                    v-model="form.mobile"
                    type="text"
                    inputmode="numeric"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': mobileError }"
                    maxlength="10"
                    placeholder="10-digit mobile"
                    required
                    @input="onMobileInput"
                />
                <div v-if="mobileError" class="invalid-feedback d-block">
                    {{ mobileError }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_license_number`" class="form-label">License Number</label>
                <input
                    :id="`${modalId}_license_number`"
                    v-model="form.license_number"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.license_number }"
                    placeholder="e.g. DL-1420110012345"
                    @input="onLicenseInput"
                />
                <div v-if="fieldErrors.license_number" class="invalid-feedback d-block">
                    {{ fieldErrors.license_number[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_address`" class="form-label">Address</label>
                <input
                    :id="`${modalId}_address`"
                    v-model="form.address"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.address }"
                    placeholder="Enter address"
                />
                <div v-if="fieldErrors.address" class="invalid-feedback d-block">
                    {{ fieldErrors.address[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_license_expiry`" class="form-label">License Expiry</label>
                <AppDateInput
                    :input-id="`${modalId}_license_expiry`"
                    v-model="form.license_expiry_date"
                    size="sm"
                    :invalid="!!fieldErrors.license_expiry_date"
                />
                <div v-if="fieldErrors.license_expiry_date" class="invalid-feedback d-block">
                    {{ fieldErrors.license_expiry_date[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_joining_date`" class="form-label">Joining Date *</label>
                <AppDateInput
                    :input-id="`${modalId}_joining_date`"
                    v-model="form.joining_date"
                    size="sm"
                    :invalid="!!joiningDateError"
                    required
                />
                <div v-if="joiningDateError" class="invalid-feedback d-block">
                    {{ joiningDateError }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_aadhaar`" class="form-label">Aadhaar Number</label>
                <input
                    :id="`${modalId}_aadhaar`"
                    v-model="form.aadhaar_number"
                    type="text"
                    inputmode="numeric"
                    maxlength="12"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.aadhaar_number }"
                    placeholder="12 digits"
                    @input="onAadhaarInput"
                />
                <div v-if="fieldErrors.aadhaar_number" class="invalid-feedback d-block">
                    {{ fieldErrors.aadhaar_number[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_pan`" class="form-label">PAN Number</label>
                <input
                    :id="`${modalId}_pan`"
                    v-model="form.pan_number"
                    type="text"
                    maxlength="10"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.pan_number }"
                    placeholder="ABCDE1234F"
                    @input="onPanInput"
                />
                <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                    {{ fieldErrors.pan_number[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_bank_account`" class="form-label">Bank Account No.</label>
                <input
                    :id="`${modalId}_bank_account`"
                    v-model="form.bank_account_number"
                    type="text"
                    inputmode="numeric"
                    class="form-control form-control-sm"
                    @input="onBankAccountInput"
                />
            </div>
            <div>
                <label :for="`${modalId}_bank_ifsc`" class="form-label">IFSC</label>
                <input
                    :id="`${modalId}_bank_ifsc`"
                    v-model="form.bank_ifsc"
                    type="text"
                    class="form-control form-control-sm"
                />
            </div>
            <div>
                <label :for="`${modalId}_emergency_name`" class="form-label">Emergency Contact Name</label>
                <input
                    :id="`${modalId}_emergency_name`"
                    v-model="form.emergency_contact_name"
                    type="text"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.emergency_contact_name }"
                />
                <div v-if="fieldErrors.emergency_contact_name" class="invalid-feedback d-block">
                    {{ fieldErrors.emergency_contact_name[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_emergency_phone`" class="form-label">Emergency Contact Mobile</label>
                <input
                    :id="`${modalId}_emergency_phone`"
                    v-model="form.emergency_contact_phone"
                    type="text"
                    inputmode="numeric"
                    maxlength="10"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': fieldErrors.emergency_contact_phone }"
                    placeholder="10-digit mobile"
                    @input="onEmergencyPhoneInput"
                />
                <div v-if="fieldErrors.emergency_contact_phone" class="invalid-feedback d-block">
                    {{ fieldErrors.emergency_contact_phone[0] }}
                </div>
            </div>
            <div>
                <label :for="`${modalId}_daily_wage`" class="form-label">Monthly Payment Base *</label>
                <input
                    :id="`${modalId}_daily_wage`"
                    v-model="form.daily_wage"
                    v-no-scroll-change
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-control form-control-sm"
                    :class="{ 'is-invalid': monthlyPaymentError }"
                    placeholder="e.g. 30000"
                    required
                />
                <div v-if="monthlyPaymentError" class="invalid-feedback d-block">
                    {{ monthlyPaymentError }}
                </div>
            </div>
            <div class="driver-form-grid__active">
                <div class="form-check">
                    <input
                        :id="`${modalId}_is_active`"
                        v-model="form.is_active"
                        class="form-check-input"
                        type="checkbox"
                    />
                    <label class="form-check-label" :for="`${modalId}_is_active`">Active</label>
                </div>
            </div>
            <p class="driver-form-grid__hint text-muted small mb-0">
                Monthly payment appears in Monthly Wage Settlement.
            </p>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="close">
                Cancel
            </button>
            <button type="submit" class="btn btn-success btn-sm" :disabled="saving">
                <i class="bi bi-check-circle"></i>
                {{ saving ? (isEdit ? 'Updating...' : 'Adding...') : isEdit ? 'Update Driver' : 'Add Driver' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
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

function onLicenseInput(event) {
    form.value.license_number = String(event.target.value ?? '').toUpperCase();
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
        if (visible) {
            fillForm(props.driver);
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

    if (Number.isNaN(wage) || wage < 1000 || wage > 500000) {
        monthlyPaymentClientError.value = 'Monthly payment base must be between 1000 and 500000.';

        return;
    }

    emit('submit', {
        name: form.value.name.trim(),
        phone: mobile,
        license_number: form.value.license_number?.trim() || null,
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

<style scoped>
.driver-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem 0.75rem;
    align-items: start;
}

.driver-form-grid__active {
    display: flex;
    align-items: flex-end;
    padding-bottom: 0.15rem;
}

.driver-form-grid__hint {
    grid-column: 1 / -1;
    font-size: 0.75rem;
    line-height: 1.3;
}

@media (max-width: 575.98px) {
    .driver-form-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
