<template>
    <div class="card mb-3 app-index-filters">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
            <span class="card-title mb-0 h6">Filters</span>
            <span v-if="active" class="badge bg-light text-dark border">Active</span>
        </div>
        <div class="card-body row g-3 align-items-end">
            <slot />
            <div
                v-if="showActions"
                class="col-12 col-md-auto d-flex flex-wrap gap-2"
                :class="actionsAlignEnd ? 'ms-md-auto' : ''"
            >
                <AppButton icon="bi bi-funnel" label="Apply" @click="$emit('apply')" />
                <AppButton variant="secondary" outline label="Clear" @click="$emit('clear')" />
            </div>
        </div>
    </div>
</template>

<script setup>
import AppButton from './AppButton.vue';

defineProps({
    active: {
        type: Boolean,
        default: false,
    },
    showActions: {
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
