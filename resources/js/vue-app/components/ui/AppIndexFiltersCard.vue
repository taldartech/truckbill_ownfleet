<template>
    <div class="card mb-3 app-index-filters">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
            <span class="card-title mb-0 h6">Search &amp; Filters</span>
            <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                <slot name="header-actions" />
                <span v-if="active" class="badge bg-light text-dark border">Active</span>
            </div>
        </div>
        <div class="card-body app-index-filters-body">
            <div class="app-index-filters-row">
                <div class="app-index-filters-fields">
                    <slot />
                </div>
                <div v-if="showActions" class="filters-actions-cell">
                    <button
                        type="button"
                        class="app-filter-action-btn app-filter-action-btn--apply"
                        title="Apply filters"
                        aria-label="Apply filters"
                        @click="$emit('apply')"
                    >
                        <i class="bi bi-funnel-fill" aria-hidden="true"></i>
                    </button>
                    <button
                        v-if="showClear"
                        type="button"
                        class="app-filter-action-btn app-filter-action-btn--reset"
                        title="Clear filters"
                        aria-label="Clear filters"
                        @click="$emit('clear')"
                    >
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    active: {
        type: Boolean,
        default: false,
    },
    showActions: {
        type: Boolean,
        default: true,
    },
    showClear: {
        type: Boolean,
        default: true,
    },
    actionsAlignEnd: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['apply', 'clear']);
</script>
