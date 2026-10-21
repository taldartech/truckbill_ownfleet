<template>
    <div class="card app-index-list-card">
        <div
            v-if="title || $slots.toolbar"
            class="card-header"
            :class="[{ 'app-index-card-header--toolbar-only': !title && $slots.toolbar }, headerClass]"
        >
            <h3 v-if="title" class="card-title mb-0">{{ title }}</h3>
            <div
                v-if="$slots.toolbar"
                class="app-index-card-toolbar d-flex flex-wrap gap-2 align-items-center"
            >
                <slot name="toolbar" />
            </div>
        </div>
        <div class="card-body">
            <slot name="filters" />
            <div v-if="loading" class="text-center py-4 text-muted">{{ loadingText }}</div>
            <div v-else class="table-responsive">
                <slot />
            </div>
        </div>
        <slot name="footer" />
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    headerClass: {
        type: [String, Array, Object],
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    loadingText: {
        type: String,
        default: 'Loading…',
    },
});
</script>
