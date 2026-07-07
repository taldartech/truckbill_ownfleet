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
            <i class="bi bi-person-badge"></i> Quick Add Reference
        </template>

        <PartyQuickAddFields
            name-label="Reference Name"
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
            :local-field-errors="localFieldErrors"
            @clear-field-error="clearLocalFieldError"
        />

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { createBroker } from '../../api/brokers';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useLocationLookups } from '../../composables/useLocationLookups';
import {
    buildPartyDuplicateFieldErrors,
    buildPartyTaxFieldErrors,
} from '../../utils/partyQuickAddValidation';
import { normalizeMobile } from '../../utils/phone';
import { normalizeGstNumber, normalizePanNumber } from '../../utils/taxIdentity';
import PartyQuickAddFields from '../parties/PartyQuickAddFields.vue';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    existingBrokers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'created']);

const name = ref('');
const gstNumber = ref('');
const panNumber = ref('');
const contactNumber = ref('');
const address = ref('');
const saving = ref(false);
const localFieldErrors = reactive({});
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

function clearLocalFieldErrors() {
    Object.keys(localFieldErrors).forEach((key) => {
        delete localFieldErrors[key];
    });
}

function clearLocalFieldError(field) {
    delete localFieldErrors[field];
}

function resetForm() {
    name.value = '';
    gstNumber.value = '';
    panNumber.value = '';
    contactNumber.value = '';
    address.value = '';
}

function validateClientSide() {
    clearLocalFieldErrors();

    const errors = {
        ...buildPartyTaxFieldErrors({
            gstNumber: gstNumber.value,
            panNumber: panNumber.value,
        }),
        ...buildPartyDuplicateFieldErrors({
            name: name.value,
            cityId: cityId.value,
            contactNumber: contactNumber.value,
            parties: props.existingBrokers,
            entityLabel: 'reference',
        }),
    };

    Object.assign(localFieldErrors, errors);

    return Object.keys(errors).length === 0;
}

function syncDuplicateChecks() {
    const duplicateErrors = buildPartyDuplicateFieldErrors({
        name: name.value,
        cityId: cityId.value,
        contactNumber: contactNumber.value,
        parties: props.existingBrokers,
        entityLabel: 'reference',
    });

    if (duplicateErrors.name) {
        localFieldErrors.name = duplicateErrors.name;
    } else {
        delete localFieldErrors.name;
    }

    if (duplicateErrors.contact_number) {
        localFieldErrors.contact_number = duplicateErrors.contact_number;
    } else {
        delete localFieldErrors.contact_number;
    }
}

watch([name, cityId], syncDuplicateChecks);
watch(contactNumber, syncDuplicateChecks);

watch(
    () => props.show,
    async (visible) => {
        if (!visible) {
            return;
        }

        resetForm();
        clearErrors();
        clearLocalFieldErrors();
        resetLocationFields();
        await initialize();
    },
);

async function submit() {
    clearErrors();

    if (!validateClientSide()) {
        formError.value = 'Please check the highlighted fields.';

        return;
    }

    saving.value = true;

    try {
        const broker = await createBroker({
            name: name.value.trim(),
            country_id: countryId.value || indiaCountry.value?.id,
            state_id: stateId.value,
            city_id: cityId.value,
            gst_number: normalizeGstNumber(gstNumber.value) || null,
            pan_number: normalizePanNumber(panNumber.value) || null,
            contact_number: normalizeMobile(contactNumber.value) || null,
            address: address.value.trim() || null,
        });
        resetForm();
        resetLocationFields();
        emit('created', broker);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add reference.');
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
