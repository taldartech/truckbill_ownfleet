<template>
    <div class="mb-3">
        <div class="d-flex justify-content-between small mb-1">
            <strong>{{ metric.label }}</strong>
            <span>{{ metric.used }} / {{ metric.max || '—' }}</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar" :class="barClass" :style="{ width: `${metric.percent}%` }"></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    metric: {
        type: Object,
        required: true,
    },
});

const barClass = computed(() => {
    const percent = props.metric.percent;

    if (props.metric.invert) {
        if (percent <= 10) return 'bg-danger';
        if (percent <= 25) return 'bg-warning';

        return 'bg-success';
    }

    if (percent >= 90) return 'bg-danger';
    if (percent >= 75) return 'bg-warning';

    return 'bg-primary';
});
</script>

<style scoped>
@media (max-width: 767.98px) {
    .d-flex.small {
        font-size: 0.72rem !important;
    }
}
</style>
