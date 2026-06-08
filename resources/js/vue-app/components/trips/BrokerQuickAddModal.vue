<template>
    <div
        v-if="show"
        class="modal fade show d-block"
        tabindex="-1"
        style="background: rgba(0, 0, 0, 0.45)"
        @click.self="$emit('close')"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-person-badge"></i> Quick Add Broker</h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <PartyQuickAddFields
                            name-label="Broker Name"
                            v-model:name="name"
                            v-model:gst-number="gstNumber"
                            v-model:pan-number="panNumber"
                            v-model:contact-number="contactNumber"
                            v-model:address="address"
                            v-model:state-id="stateId"
                            v-model:city-id="cityId"
                            :states="states"
                            :cities="cities"
                            :india-country="indiaCountry"
                            :field-errors="fieldErrors"
                        />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                        <AppButton type="submit" label="Save" :loading="saving" />
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { createBroker } from '../../api/brokers';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { normalizeMobile } from '../../utils/phone';
import PartyQuickAddFields from '../parties/PartyQuickAddFields.vue';
import AppButton from '../ui/AppButton.vue';

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
        const broker = await createBroker({
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
        emit('created', broker);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add broker.');
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
