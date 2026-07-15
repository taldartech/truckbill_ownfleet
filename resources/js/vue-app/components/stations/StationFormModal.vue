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
            <i class="bi bi-geo-alt"></i>
            {{ isEdit ? 'Edit Station' : 'Add Station' }}
        </template>

        <div class="row g-2">
            <IndiaLocationFields
                v-model:state-id="stateId"
                v-model:city-id="cityId"
                :states="states"
                :cities="cities"
                :field-errors="fieldErrors"
            />

            <div class="col-12">
                <label class="form-label">Station Name *</label>
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
                <div class="form-text">Example: Depot, Yard, or local area name.</div>
            </div>
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
import { useLocationLookups } from '../../composables/useLocationLookups';
import { buildStationFields } from '../../utils/masterCrudSaveConfirm';

const props = defineProps({
    show: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    station: { type: Object, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.station !== null);

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
});

function resetForm() {
    form.name = '';
}

function fillForm(station) {
    if (!station) {
        resetForm();
        return;
    }
    form.name = station.name ?? '';
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            fillForm(props.station);
            if (props.station) {
                await initialize({
                    countryId: props.station.country_id || countryId.value,
                    stateId: props.station.state_id,
                    cityId: props.station.city_id,
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
    };

    const fields = buildStationFields(payload, props.station, {
        states: states.value,
        cities: cities.value,
    });

    emit('submit', { payload, fields });
}
</script>
