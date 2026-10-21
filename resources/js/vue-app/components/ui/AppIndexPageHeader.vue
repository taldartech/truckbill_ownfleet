<template>
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 mb-md-4">
        <div>
            <h3 class="mb-0 fs-5 fs-md-4 fw-bold">{{ title }}</h3>
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
