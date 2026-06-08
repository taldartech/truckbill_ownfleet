<template>
    <div class="page-header">
        <div>
            <h3 class="mb-0">{{ title }}</h3>
            <p v-if="subtitle" class="text-muted small mb-0">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="d-flex flex-wrap gap-2 align-items-center">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>
