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
            <i class="bi bi-truck"></i> Quick Add Truck Type
        </template>

        <label class="form-label">Type Name *</label>
        <input
            v-model="name"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.name }"
            placeholder="e.g. Dumper"
            required
        />
        <div v-if="fieldErrors.name" class="invalid-feedback d-block">
            {{ fieldErrors.name[0] }}
        </div>
        <div class="form-text">Saved automatically in uppercase code. Types cannot be deleted later.</div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save Type" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { createTruckType } from '../../api/truckTypes';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const name = ref('');
const saving = ref(false);

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            name.value = '';
            clearErrors();
        }
    },
);

async function submit() {
    clearErrors();
    saving.value = true;

    try {
        const truckType = await createTruckType({
            name: name.value.trim(),
        });
        emit('created', truckType);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add truck type.');
    } finally {
        saving.value = false;
    }
}
</script>
