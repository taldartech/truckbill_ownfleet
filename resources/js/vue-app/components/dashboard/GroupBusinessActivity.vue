<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-route me-2"></i>
                    Business Activity
                </h5>
                <p class="group-panel__subtitle">Trips started in the period</p>
            </div>
            <div class="group-activity__total">
                <strong>{{ trips.total }}</strong>
                trips
            </div>
        </header>

        <div class="group-stat-grid group-activity__trips">
            <div v-for="status in tripStatuses" :key="status.key" class="group-stat">
                <span class="group-stat__label">
                    <span class="group-dot" :style="{ background: status.color }"></span>
                    {{ status.label }}
                </span>
                <span class="group-stat__value" :title="status.hint">{{ status.count }}</span>
            </div>
        </div>

        <div class="group-activity__documents">
            <span class="group-activity__document" title="Loaded customer legs (bilties / LRs)">
                <i class="fas fa-file-lines"></i> Bilties / LRs <strong>{{ activity.lrs_count }}</strong>
            </span>
            <span class="group-activity__document" title="Invoices raised in the period">
                <i class="fas fa-file-invoice"></i> Invoices <strong>{{ activity.invoices_count }}</strong>
            </span>
            <span class="group-activity__document" :title="`${formatMoney(activity.payments_amount)} received`">
                <i class="fas fa-hand-holding-dollar"></i> Payments <strong>{{ activity.payments_count }}</strong>
                <small>{{ formatMoneyCompact(activity.payments_amount) }}</small>
            </span>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { formatMoney, formatMoneyCompact } from '../../utils/formatMoney';
import './groupDashboard.css';

const props = defineProps({
    activity: {
        type: Object,
        required: true,
    },
});

const trips = computed(() => props.activity.trips ?? {});

function shareOf(count) {
    const total = Number(trips.value.total ?? 0);

    return total > 0 ? `${Math.round((Number(count ?? 0) / total) * 100)}% of trips` : '—';
}

const tripStatuses = computed(() => [
    {
        key: 'completed',
        label: 'Completed',
        color: '#2fb36f',
        count: trips.value.completed ?? 0,
        hint: `${trips.value.invoiced ?? 0} invoiced`,
    },
    {
        key: 'in_progress',
        label: 'In Progress',
        color: '#0284c7',
        count: trips.value.in_progress ?? 0,
        hint: shareOf(trips.value.in_progress),
    },
    {
        key: 'pending',
        label: 'Pending',
        color: '#f59e0b',
        count: trips.value.pending ?? 0,
        hint: shareOf(trips.value.pending),
    },
    {
        key: 'cancelled',
        label: 'Cancelled',
        color: '#ef4444',
        count: trips.value.cancelled ?? 0,
        hint: shareOf(trips.value.cancelled),
    },
]);
</script>

<style scoped>
.group-activity__total {
    font-size: 0.8rem;
    color: #64748b;
}

.group-activity__total strong {
    font-size: 1.15rem;
    color: #0f172a;
    margin-right: 0.15rem;
}

.group-activity__trips {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
}

@container (max-width: 440px) {
    .group-activity__trips {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.group-activity__documents {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.75rem;
}

.group-activity__document {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    font-size: 0.74rem;
    color: #475569;
}

.group-activity__document i {
    color: var(--brand-primary, #2fb36f);
}

.group-activity__document strong {
    color: #0f172a;
}

.group-activity__document small {
    color: #94a3b8;
}
</style>
