<template>
    <span class="group-delta" :class="toneClass" :title="title">
        <i v-if="icon" :class="icon" aria-hidden="true"></i>
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    changePercent: {
        type: Number,
        default: null,
    },
    current: {
        type: Number,
        default: 0,
    },
    /** When true an increase is unfavourable (e.g. expenses). */
    inverse: {
        type: Boolean,
        default: false,
    },
    comparisonLabel: {
        type: String,
        default: 'vs previous period',
    },
});

const hasChange = computed(() => props.changePercent !== null && props.changePercent !== undefined);

const isFavourable = computed(() => {
    if (!hasChange.value || props.changePercent === 0) {
        return null;
    }

    const isIncrease = props.changePercent > 0;

    return props.inverse ? !isIncrease : isIncrease;
});

const label = computed(() => {
    if (!hasChange.value) {
        return props.current > 0 ? 'New' : '—';
    }

    return `${Math.abs(props.changePercent).toFixed(1)}%`;
});

const icon = computed(() => {
    if (!hasChange.value || props.changePercent === 0) {
        return null;
    }

    return props.changePercent > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
});

const toneClass = computed(() => {
    if (!hasChange.value) {
        return props.current > 0 ? 'group-delta--new' : 'group-delta--neutral';
    }

    if (isFavourable.value === null) {
        return 'group-delta--neutral';
    }

    return isFavourable.value ? 'group-delta--up' : 'group-delta--down';
});

const title = computed(() => (hasChange.value ? `${props.changePercent}% ${props.comparisonLabel}` : props.comparisonLabel));
</script>

<style scoped>
.group-delta {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
}

.group-delta i {
    font-size: 0.6rem;
}

.group-delta--up {
    background: #ecfdf5;
    color: #059669;
}

.group-delta--down {
    background: #fef2f2;
    color: #dc2626;
}

.group-delta--new {
    background: #eff6ff;
    color: #2563eb;
}

.group-delta--neutral {
    background: #f1f5f9;
    color: #64748b;
}
</style>
