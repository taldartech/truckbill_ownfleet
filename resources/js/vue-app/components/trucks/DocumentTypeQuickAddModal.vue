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
            <i class="bi bi-file-earmark-plus"></i> Quick Add Document Type
        </template>

        <label class="form-label">Type Name *</label>
        <input
            v-model="name"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': fieldErrors.name }"
            placeholder="e.g. Pollution Certificate"
            required
        />
        <div v-if="fieldErrors.name" class="invalid-feedback d-block">
            {{ fieldErrors.name[0] }}
        </div>
        <div class="form-text">Code auto-generated. Shared across all trucks in this company.</div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">Cancel</button>
            <AppButton type="submit" label="Save Type" :loading="saving" />
        </template>
    </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { createDocumentType } from '../../api/documentTypes';
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
        const documentType = await createDocumentType({
            name: name.value.trim(),
        });
        emit('created', documentType);
        emit('close');
    } catch (error) {
        applyApiError(error, 'Failed to add document type.');
    } finally {
        saving.value = false;
    }
}
</script>
