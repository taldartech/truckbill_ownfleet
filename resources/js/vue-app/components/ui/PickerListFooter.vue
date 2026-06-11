<template>
    <div v-if="visibleCount > 0" class="picker-count-footer">
        <span>{{ countLabel }}</span>
        <span v-if="scrollHint" class="picker-count-footer__hint">{{ scrollHint }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    visibleCount: { type: Number, default: 0 },
    totalCount: { type: Number, default: null },
    scrollThreshold: { type: Number, default: 6 },
    loadMoreAvailable: { type: Boolean, default: false },
});

const countLabel = computed(() => {
    const visible = props.visibleCount;
    const total = props.totalCount ?? visible;

    if (props.totalCount !== null && total > visible) {
        return `Showing ${visible} of ${total}`;
    }

    const noun = visible === 1 ? 'option' : 'options';

    return `${visible} ${noun}`;
});

const scrollHint = computed(() => {
    if (props.loadMoreAvailable) {
        return 'Scroll for more';
    }

    if (props.visibleCount > props.scrollThreshold) {
        return 'Scroll for more';
    }

    return '';
});
</script>

<style scoped>
.picker-count-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.4rem 0.65rem;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    font-size: 0.75rem;
    color: #6c757d;
}

.picker-count-footer__hint {
    font-size: 0.7rem;
    color: #868e96;
    white-space: nowrap;
}
</style>
