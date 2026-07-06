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
            <i class="bi bi-building"></i> Quick Add Customer
        </template>

        <PartyQuickAddFields
            name-label="Customer Name"
            v-model:name="name"
            v-model:gst-number="gstNumber"
            v-model:pan-number="panNumber"
            v-model:contact-number="contactNumber"
            v-model:address="address"
            v-model:state-id="stateId"
            v-model:city-id="cityId"
            :states="states"
            :cities="cities"
            :field-errors="fieldErrors"
        />

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { createCustomer } from '../../api/customers';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { normalizeMobile } from '../../utils/phone';
import PartyQuickAddFields from '../parties/PartyQuickAddFields.vue';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const name = ref('');
const gstNumber = ref('');
const panNumber = ref('');
const contactNumber = ref('');
const address = ref('');
const saving = ref(false);
const { fieldErrors, formError, applyApiError, clearErrors } = useApiFormErrors();

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

function resetForm() {
    name.value = '';
    gstNumber.value = '';
    panNumber.value = '';
    contactNumber.value = '';
    address.value = '';
}

watch(
    () => props.show,
    async (visible) => {
        if (!visible) {
            return;
        }

        resetForm();
        clearErrors();
        resetLocationFields();
        await initialize();
    },
);

async function submit() {
    clearErrors();
    saving.value = true;

    try {
        const customer = await createCustomer({
            name: name.value.trim(),
            country_id: countryId.value || indiaCountry.value?.id,
            state_id: stateId.value,
            city_id: cityId.value,
            gst_number: gstNumber.value.trim() || null,
            pan_number: panNumber.value.trim().toUpperCase() || null,
            contact_number: normalizeMobile(contactNumber.value) || null,
            address: address.value.trim() || null,
        });
        resetForm();
        resetLocationFields();
        emit('created', customer);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add customer.');
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    if (props.show) {
        await initialize();
    }
});
</script>
