<template>
    <div
        v-if="show"
        class="modal fade show d-block"
        tabindex="-1"
        style="background: rgba(0, 0, 0, 0.45)"
        @click.self="$emit('close')"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-geo-alt"></i> Quick Add Station</h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" :value="indiaCountry?.name || 'India'" disabled />
                        </div>

                        <div class="mb-3">
                            <label class="form-label">State *</label>
                            <SearchableSelect
                                v-model="stateId"
                                :items="states"
                                placeholder="Select state"
                                search-placeholder="Search state…"
                            />
                        </div>

                        <div class="mb-3">
                            <label class="form-label">City *</label>
                            <SearchableSelect
                                v-model="cityId"
                                :items="cities"
                                placeholder="Select city"
                                search-placeholder="Search city…"
                                :disabled="!stateId"
                            />
                        </div>

                        <label class="form-label">Station Name *</label>
                        <input v-model="name" type="text" class="form-control" required />
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
import { createStation } from '../../api/stations';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useLocationLookups } from '../../composables/useLocationLookups';
import AppButton from '../ui/AppButton.vue';
import SearchableSelect from '../ui/SearchableSelect.vue';

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
