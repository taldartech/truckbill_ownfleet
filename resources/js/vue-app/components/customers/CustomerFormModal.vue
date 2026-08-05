<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        size="lg"
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i class="bi bi-building"></i>
            {{ isEdit ? 'Edit Customer' : 'Add Customer' }}
        </template>

        <div class="row g-2">
            <div class="col-12">
                <label class="form-label">Customer Name *</label>
                <input
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

            <div class="col-md-4">
                <label class="form-label">GST Number</label>
                <input
                    v-model="form.gst_number"
                    type="text"
                    class="form-control text-uppercase"
                    :class="{ 'is-invalid': fieldErrors.gst_number }"
                />
                <div v-if="fieldErrors.gst_number" class="invalid-feedback d-block">
                    {{ fieldErrors.gst_number[0] }}
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">PAN Number</label>
                <input
                    v-model="form.pan_number"
                    type="text"
                    class="form-control text-uppercase"
                    :class="{ 'is-invalid': fieldErrors.pan_number }"
                    maxlength="10"
                />
                <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                    {{ fieldErrors.pan_number[0] }}
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contact Number *</label>
                <input
                    :value="form.contact_number"
                    type="text"
                    inputmode="numeric"
                    maxlength="10"
                    class="form-control"
                    placeholder="10-digit mobile"
                    required
                    :class="{ 'is-invalid': fieldErrors.contact_number }"
                    @input="form.contact_number = normalizeMobile($event.target.value)"
                />
                <div v-if="fieldErrors.contact_number" class="invalid-feedback d-block">
                    {{ fieldErrors.contact_number[0] }}
                </div>
            </div>
            <IndiaLocationFields
                v-model:state-id="stateId"
                v-model:city-id="cityId"
                :states="states"
                :cities="cities"
                :field-errors="fieldErrors"
            />
            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea
                    v-model="form.address"
                    class="form-control"
                    rows="2"
                    :class="{ 'is-invalid': fieldErrors.address }"
                />
                <div v-if="fieldErrors.address" class="invalid-feedback d-block">
                    {{ fieldErrors.address[0] }}
                </div>
            </div>
            <CustomerBrokerToggle
                v-model="form.is_broker"
                input-id="customer_is_broker"
                :invalid="Boolean(fieldErrors.is_broker)"
                :error-message="fieldErrors.is_broker?.[0] ?? ''"
            />
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="close">Cancel</button>
            <AppButton type="submit" label="Save" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import AppModal from '../ui/AppModal.vue';
import AppButton from '../ui/AppButton.vue';
import IndiaLocationFields from '../locations/IndiaLocationFields.vue';
import CustomerBrokerToggle from './CustomerBrokerToggle.vue';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { buildPartyLocationFields } from '../../utils/masterCrudSaveConfirm';
import { normalizeMobile } from '../../utils/phone';

const props = defineProps({
    show: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    customer: { type: Object, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.customer !== null);

const {
    states,
    cities,
    countryId,
    stateId,
    cityId,
    indiaCountry,
    initialize,
    resetLocationFields,
} = useLocationLookups();

const form = reactive({
    name: '',
    gst_number: '',
    pan_number: '',
    contact_number: '',
    address: '',
    is_broker: false,
});

function resetForm() {
    form.name = '';
    form.gst_number = '';
    form.pan_number = '';
    form.contact_number = '';
    form.address = '';
    form.is_broker = false;
}

function fillForm(customer) {
    if (!customer) {
        resetForm();
        return;
    }
    form.name = customer.name ?? '';
    form.gst_number = customer.gst_number ?? '';
    form.pan_number = customer.pan_number ?? '';
    form.contact_number = normalizeMobile(customer.contact_number ?? '');
    form.address = customer.address ?? '';
    form.is_broker = Boolean(customer.is_broker);
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            fillForm(props.customer);
            if (props.customer) {
                await initialize({
                    countryId: props.customer.country_id || countryId.value,
                    stateId: props.customer.state_id,
                    cityId: props.customer.city_id,
                });
            } else {
                resetLocationFields();
                await initialize();
            }
        }
    }
);

function close() {
    emit('close');
}

function submit() {
    const payload = {
        name: form.name.trim(),
        country_id: countryId.value || indiaCountry.value?.id,
        state_id: stateId.value,
        city_id: cityId.value,
        gst_number: form.gst_number.trim().toUpperCase() || null,
        pan_number: form.pan_number.trim().toUpperCase() || null,
        contact_number: normalizeMobile(form.contact_number),
        address: form.address.trim() || null,
        is_broker: Boolean(form.is_broker),
    };

    const fields = buildPartyLocationFields(payload, props.customer, {
        states: states.value,
        cities: cities.value,
    });

    emit('submit', { payload, fields });
}
</script>
