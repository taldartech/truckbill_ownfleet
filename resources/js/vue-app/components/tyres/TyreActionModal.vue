<template>
    <AppModal
        :show="true"
        :title="title"
        :form-error="formError"
        :loading="saving"
        as-form
        body-class="app-modal__body--overflow-visible"
        @close="$emit('close')"
        @submit="$emit('submit')"
    >
        <slot />

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">
                Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Saving…' : 'Save' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import AppModal from '../ui/AppModal.vue';

defineProps({
    title: { type: String, required: true },
    formError: { type: String, default: '' },
    saving: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);
</script>
