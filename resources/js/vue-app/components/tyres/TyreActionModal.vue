<template>
    <div
        class="modal fade show d-block"
        tabindex="-1"
        style="background: rgba(0, 0, 0, 0.45)"
        @click.self="$emit('close')"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="$emit('submit')">
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <slot />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" :disabled="saving" @click="$emit('close')">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    formError: { type: String, default: '' },
    saving: { type: Boolean, default: false },
});

defineEmits(['close', 'submit']);
</script>
