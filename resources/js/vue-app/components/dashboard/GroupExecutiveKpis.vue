<template>
    <div class="group-kpis">
        <article v-for="card in cards" :key="card.key" class="group-kpis__card" :class="`group-kpis__card--${card.key}`">
            <div class="group-kpis__head">
                <span class="group-kpis__icon"><i :class="card.icon"></i></span>
                <span class="group-kpis__label">{{ card.label }}</span>
            </div>
            <div class="group-kpis__value" :title="card.title">{{ card.value }}</div>
            <div class="group-kpis__foot">
                <GroupDeltaBadge
                    v-if="card.metric"
                    :change-percent="card.metric.change_percent"
                    :current="card.metric.current"
                    :inverse="card.inverse"
                    :comparison-label="comparisonLabel"
                />
                <span v-else-if="card.marginDelta !== undefined" class="group-kpis__points" :class="marginToneClass">
                    {{ card.marginDelta }}
                </span>
                <span class="group-kpis__hint">{{ card.hint }}</span>
            </div>
        </article>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatMoney, formatMoneyCompact } from '../../utils/formatMoney';
import GroupDeltaBadge from './GroupDeltaBadge.vue';

const props = defineProps({
    executive: {
        type: Object,
        required: true,
    },
    comparisonLabel: {
        type: String,
        default: 'vs previous period',
    },
});

const hasCurrentRevenue = computed(() => Number(props.executive.revenue?.current ?? 0) !== 0);
const hasPreviousRevenue = computed(() => Number(props.executive.revenue?.previous ?? 0) !== 0);

const marginChange = computed(() => {
    if (!hasCurrentRevenue.value || !hasPreviousRevenue.value) {
        return null;
    }

    const margin = props.executive.margin ?? {};

    return Number((Number(margin.current) - Number(margin.previous)).toFixed(1));
});

const marginToneClass = computed(() => {
    if (marginChange.value === null || marginChange.value === 0) {
        return 'group-kpis__points--neutral';
    }

    return marginChange.value > 0 ? 'group-kpis__points--up' : 'group-kpis__points--down';
});

const cards = computed(() => {
    const { revenue, expenses, profit, margin } = props.executive;

    return [
        {
            key: 'companies',
            label: 'Companies',
            icon: 'fas fa-building',
            value: String(props.executive.companies_count ?? 0),
            hint: 'in this view',
        },
        {
            key: 'revenue',
            label: 'Revenue',
            icon: 'fas fa-indian-rupee-sign',
            value: formatMoneyCompact(revenue.current),
            title: formatMoney(revenue.current),
            metric: revenue,
            hint: 'vs previous',
        },
        {
            key: 'expenses',
            label: 'Expenses',
            icon: 'fas fa-receipt',
            value: formatMoneyCompact(expenses.current),
            title: formatMoney(expenses.current),
            metric: expenses,
            inverse: true,
            hint: 'direct trip cost',
        },
        {
            key: 'profit',
            label: 'Profit',
            icon: 'fas fa-sack-dollar',
            value: formatMoneyCompact(profit.current),
            title: formatMoney(profit.current),
            metric: profit,
            hint: 'vs previous',
        },
        {
            key: 'margin',
            label: 'Margin',
            icon: 'fas fa-percent',
            value: hasCurrentRevenue.value ? `${margin.current}%` : '—',
            marginDelta:
                marginChange.value === null
                    ? '—'
                    : `${marginChange.value > 0 ? '+' : ''}${marginChange.value} pts`,
            hint: hasPreviousRevenue.value ? `was ${margin.previous}%` : 'no earlier data',
        },
    ];
});
</script>

<style scoped>
.group-kpis {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.75rem;
}

.group-kpis__card {
    --kpi-accent: #2fb36f;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.7rem 0.9rem;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-top: 3px solid var(--kpi-accent);
    border-radius: 1rem;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    min-width: 0;
}

.group-kpis__card--companies {
    --kpi-accent: #6366f1;
}

.group-kpis__card--expenses {
    --kpi-accent: #f97316;
}

.group-kpis__card--profit {
    --kpi-accent: #059669;
}

.group-kpis__card--margin {
    --kpi-accent: #0284c7;
}

.group-kpis__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.group-kpis__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 0.45rem;
    background: color-mix(in srgb, var(--kpi-accent) 12%, transparent);
    color: var(--kpi-accent);
    font-size: 0.68rem;
}

.group-kpis__label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
}

.group-kpis__value {
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.group-kpis__foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
}

.group-kpis__hint {
    font-size: 0.7rem;
    color: #94a3b8;
}

.group-kpis__points {
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
}

.group-kpis__points--up {
    background: #ecfdf5;
    color: #059669;
}

.group-kpis__points--down {
    background: #fef2f2;
    color: #dc2626;
}

.group-kpis__points--neutral {
    background: #f1f5f9;
    color: #64748b;
}

@container group-dashboard (max-width: 760px) {
    .group-kpis {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@container group-dashboard (max-width: 480px) {
    .group-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
