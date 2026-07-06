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
            <i class="bi bi-geo-alt"></i> Quick Add Station
        </template>

        <div class="row g-2">
            <IndiaLocationFields
                v-model:state-id="stateId"
                v-model:city-id="cityId"
                :states="states"
                :cities="cities"
            />

            <div class="col-12">
                <label class="form-label">Station Name *</label>
                <input v-model="name" type="text" class="form-control" required />
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { createStation } from '../../api/stations';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useLocationLookups } from '../../composables/useLocationLookups';
import IndiaLocationFields from '../locations/IndiaLocationFields.vue';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const name = ref('');
const saving = ref(false);
const { formError, applyApiError, clearErrors } = useApiFormErrors();

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

watch(
    () => props.show,
    async (visible) => {
        if (!visible) {
            return;
        }

        name.value = '';
        clearErrors();
        resetLocationFields();
        await initialize();
    }
);

async function submit() {
    clearErrors();
    saving.value = true;

    try {
        const station = await createStation({
            name: name.value.trim(),
            country_id: countryId.value || indiaCountry.value?.id,
            state_id: stateId.value,
            city_id: cityId.value,
        });
        name.value = '';
        stateId.value = '';
        cityId.value = '';
        emit('created', station);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add station.');
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
