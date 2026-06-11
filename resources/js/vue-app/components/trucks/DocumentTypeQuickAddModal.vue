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
                    <h5 class="modal-title"><i class="bi bi-file-earmark-plus"></i> Quick Add Document Type</h5>
                    <button type="button" class="btn-close btn-close-white" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                        <AppButton type="submit" label="Save Type" :loading="saving" />
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { createDocumentType } from '../../api/documentTypes';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import AppButton from '../ui/AppButton.vue';

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
