<template>
    <div class="app-report-filters-panel">
        <div class="app-report-filters-panel__inner">
            <div class="app-index-filters-fields">
                <slot />
            </div>
            <div class="app-report-filters-panel__actions">
                <button
                    type="button"
                    class="btn btn-brand-primary btn-sm app-report-filters-panel__btn"
                    :disabled="loading"
                    @click="$emit('search')"
                >
                    <i class="fas fa-search" aria-hidden="true"></i>
                    Search
                </button>
                <button
                    type="button"
                    class="btn btn-back btn-sm app-report-filters-panel__btn"
                    :disabled="loading"
                    @click="$emit('reset')"
                >
                    <i class="fas fa-rotate-left" aria-hidden="true"></i>
                    Reset
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['search', 'reset']);
</script>
