<template>
    <div class="card app-index-list-card">
        <div
            v-if="title || $slots.toolbar"
            class="card-header d-flex flex-row flex-nowrap align-items-center justify-content-between gap-2"
            :class="[{ 'app-index-card-header--toolbar-only': !title && $slots.toolbar }, headerClass]"
        >
            <h3 v-if="title" class="card-title mb-0 text-nowrap">{{ title }}</h3>
            <div
                v-if="$slots.toolbar"
                class="app-index-card-toolbar d-flex flex-row flex-nowrap align-items-center gap-1 gap-md-2 ms-auto flex-shrink-0"
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
