<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-truck me-2"></i>
                    Fleet Overview
                </h5>
                <p class="group-panel__subtitle">Right now · click Idle for the list</p>
            </div>
            <div class="group-fleet__total">
                <strong>{{ overview.total_trucks }}</strong>
                trucks
            </div>
        </header>

        <div v-if="overview.total_trucks === 0" class="group-panel__empty">
            <i class="fas fa-truck"></i>
            No trucks added yet.
        </div>

        <template v-else>
            <div class="group-fleet__bar" role="img" :aria-label="barLabel">
                <span
                    v-for="status in visibleStatuses"
                    :key="status.key"
                    class="group-fleet__segment"
                    :style="{ width: `${status.share}%`, background: status.color }"
                    :title="`${status.label}: ${status.count}`"
                ></span>
            </div>

            <div class="group-stat-grid group-fleet__grid">
                <component
                    :is="status.clickable ? 'button' : 'div'"
                    v-for="status in statuses"
                    :key="status.key"
                    :type="status.clickable ? 'button' : undefined"
                    class="group-stat"
                    @click="status.clickable && $emit('show-idle')"
                >
                    <span class="group-stat__label">
                        <span class="group-dot" :style="{ background: status.color }"></span>
                        {{ status.label }}
                    </span>
                    <span class="group-stat__value">
                        {{ status.count }}<small class="group-fleet__share">{{ status.share }}%</small>
                    </span>
                </component>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import './groupDashboard.css';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
});

defineEmits(['show-idle']);

const STATUS_DEFINITIONS = [
    { key: 'on_trip', label: 'On Trip', color: '#2fb36f' },
    { key: 'available', label: 'Available', color: '#0284c7' },
    { key: 'idle', label: 'Idle', color: '#f59e0b', clickable: true },
    { key: 'maintenance', label: 'Maintenance', color: '#ef4444' },
    { key: 'unavailable', label: 'Unavailable', color: '#94a3b8' },
];

const statuses = computed(() => {
    const total = Number(props.overview.total_trucks ?? 0);

    return STATUS_DEFINITIONS.map((status) => {
        const count = Number(props.overview[status.key] ?? 0);

        return {
            ...status,
            count,
            share: total > 0 ? Number(((count / total) * 100).toFixed(1)) : 0,
        };
    });
});

const visibleStatuses = computed(() => statuses.value.filter((status) => status.count > 0));

const barLabel = computed(() => statuses.value.map((status) => `${status.label} ${status.count}`).join(', '));
</script>

<style scoped>
.group-fleet__total {
    font-size: 0.8rem;
    color: #64748b;
}

.group-fleet__total strong {
    font-size: 1.15rem;
    color: #0f172a;
    margin-right: 0.15rem;
}

.group-fleet__bar {
    display: flex;
    height: 8px;
    margin-bottom: 0.75rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.group-fleet__segment {
    height: 100%;
    transition: width 0.35s ease;
}

.group-fleet__grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.5rem;
}

@container (max-width: 540px) {
    .group-fleet__grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.group-fleet__share {
    margin-left: 0.3rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: #94a3b8;
}
</style>
