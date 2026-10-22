<template>
    <div :id="panelId" class="app-index-collapsible-filters">
        <div class="app-index-collapsible-filters__inner d-flex flex-wrap align-items-end gap-2 p-2 p-md-3">
            <div class="app-index-filters-fields d-contents">
                <slot />
            </div>
            <div class="app-index-collapsible-filters__actions d-flex align-items-center gap-2 mt-2 mt-md-0 ms-md-auto w-100 w-md-auto">
                <button
                    type="button"
                    class="btn btn-brand-primary btn-sm app-index-collapsible-filters__btn flex-fill flex-md-grow-0"
                    :disabled="loading"
                    title="Search"
                    aria-label="Search"
                    @click="$emit('search')"
                >
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span class="app-index-filter-btn-text ms-1">Search</span>
                </button>
                <button
                    type="button"
                    class="btn btn-back btn-sm app-index-collapsible-filters__btn flex-fill flex-md-grow-0"
                    :disabled="loading"
                    title="Reset"
                    aria-label="Reset"
                    @click="$emit('reset')"
                >
                    <i class="fas fa-rotate-left" aria-hidden="true"></i>
                    <span class="app-index-filter-btn-text ms-1">Reset</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    panelId: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['search', 'reset']);
</script>
