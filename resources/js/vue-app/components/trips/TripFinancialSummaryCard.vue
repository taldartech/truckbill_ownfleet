<template>
    <div
        class="card trip-financial-summary"
        :class="isProfit ? 'trip-financial-summary--profit' : 'trip-financial-summary--loss'"
    >
        <div class="trip-financial-summary__header">
            <div class="trip-financial-summary__header-text">
                <strong class="trip-financial-summary__title">Financial Summary</strong>
                <span class="trip-financial-summary__subtitle">Revenue vs expenses</span>
            </div>
            <div
                class="trip-financial-summary__badge"
                :class="isProfit ? 'is-profit' : 'is-loss'"
            >
                {{ isProfit ? 'Profit' : 'Loss' }}
                ₹{{ formatMoney(Math.abs(profitAmount)) }}
            </div>
        </div>

        <div class="trip-financial-summary__body">
            <div class="trip-financial-summary__stats">
                <div class="trip-financial-summary__stat">
                    <span class="trip-financial-summary__stat-label">Freight</span>
                    <span class="trip-financial-summary__stat-value">₹{{ formatMoney(freightAmount) }}</span>
                </div>
                <div v-if="customAmount > 0" class="trip-financial-summary__stat">
                    <span class="trip-financial-summary__stat-label">Additional</span>
                    <span class="trip-financial-summary__stat-value">₹{{ formatMoney(customAmount) }}</span>
                </div>
                <div class="trip-financial-summary__stat">
                    <span class="trip-financial-summary__stat-label">Expenses</span>
                    <span class="trip-financial-summary__stat-value trip-financial-summary__stat-value--expense">
                        ₹{{ formatMoney(expenseAmount) }}
                    </span>
                </div>
                <div class="trip-financial-summary__stat trip-financial-summary__stat--result">
                    <span class="trip-financial-summary__stat-label">
                        {{ isProfit ? 'Net Profit' : 'Net Loss' }}
                    </span>
                    <span
                        class="trip-financial-summary__stat-value"
                        :class="isProfit ? 'is-profit' : 'is-loss'"
                    >
                        ₹{{ formatMoney(profitAmount) }}
                    </span>
                </div>
            </div>

            <div
                class="trip-financial-summary__bar"
                role="img"
                :aria-label="barAriaLabel"
            >
                <div
                    class="trip-financial-summary__bar-freight"
                    :style="{ width: freightBarPct + '%' }"
                ></div>
                <div
                    class="trip-financial-summary__bar-expense"
                    :style="{ width: expenseBarPct + '%' }"
                ></div>
            </div>
            <div class="trip-financial-summary__legend">
                <span><i class="trip-financial-summary__dot trip-financial-summary__dot--freight"></i> Freight</span>
                <span><i class="trip-financial-summary__dot trip-financial-summary__dot--expense"></i> Expenses</span>
                <span class="trip-financial-summary__margin">Margin {{ marginLabel }}</span>
            </div>

            <div v-if="perKmMetrics.length > 0" class="trip-financial-summary__per-km">
                <div
                    v-for="metric in perKmMetrics"
                    :key="metric.label"
                    class="trip-financial-summary__per-km-item"
                >
                    <span class="trip-financial-summary__per-km-label">{{ metric.label }}</span>
                    <strong
                        class="trip-financial-summary__per-km-value"
                        :class="metric.toneClass"
                    >
                        {{ metric.value }}
                    </strong>
                </div>
            </div>

            <div v-if="expenseBreakdown.length > 0" class="trip-financial-summary__breakdown">
                <div class="trip-financial-summary__breakdown-title">Expense Breakdown</div>
                <ul class="trip-financial-summary__breakdown-list">
                    <li
                        v-for="row in expenseBreakdown"
                        :key="row.key"
                        class="trip-financial-summary__breakdown-row"
                    >
                        <div class="trip-financial-summary__breakdown-meta">
                            <span class="trip-financial-summary__breakdown-label">{{ row.label }}</span>
                            <span class="trip-financial-summary__breakdown-amount">
                                ₹{{ formatMoney(row.amount) }}
                                <span class="text-muted">· {{ row.shareLabel }}</span>
                            </span>
                        </div>
                        <div class="trip-financial-summary__breakdown-track" aria-hidden="true">
                            <div
                                class="trip-financial-summary__breakdown-fill"
                                :style="{ width: row.sharePct + '%' }"
                            ></div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { expenseTypeLabel } from '../../api/trips';

const props = defineProps({
    trip: {
        type: Object,
        required: true,
    },
    expenses: {
        type: Array,
        default: () => [],
    },
});

const freightAmount = computed(() => Number(props.trip?.total_freight_amount ?? 0));
const customAmount = computed(() => Number(props.trip?.total_custom_amount ?? 0));
const revenueAmount = computed(() => freightAmount.value + customAmount.value);
const expenseAmount = computed(() => Number(props.trip?.total_expense_amount ?? 0));
const profitAmount = computed(() => {
    if (props.trip?.profit_amount != null && props.trip?.profit_amount !== '') {
        return Number(props.trip.profit_amount);
    }

    return revenueAmount.value - expenseAmount.value;
});
const isProfit = computed(() => profitAmount.value >= 0);
const distanceKm = computed(() => Number(props.trip?.total_distance_km ?? 0));

const barScale = computed(() => Math.max(revenueAmount.value, expenseAmount.value, 1));
const freightBarPct = computed(() => Math.min(100, (revenueAmount.value / barScale.value) * 100));
const expenseBarPct = computed(() => Math.min(100, (expenseAmount.value / barScale.value) * 100));

const marginLabel = computed(() => {
    if (!(revenueAmount.value > 0)) {
        return '—';
    }

    return `${((profitAmount.value / revenueAmount.value) * 100).toFixed(1)}%`;
});

const barAriaLabel = computed(
    () =>
        `Revenue ₹${formatMoney(revenueAmount.value)}, expenses ₹${formatMoney(expenseAmount.value)}, ` +
        `${isProfit.value ? 'profit' : 'loss'} ₹${formatMoney(Math.abs(profitAmount.value))}`,
);

const perKmMetrics = computed(() => {
    if (!(distanceKm.value > 0)) {
        return [];
    }

    const freightPerKm = revenueAmount.value / distanceKm.value;
    const expensePerKm = expenseAmount.value / distanceKm.value;
    const profitPerKm = profitAmount.value / distanceKm.value;

    return [
        {
            label: 'Revenue / KM',
            value: `₹${formatMoney(freightPerKm)}`,
            toneClass: '',
        },
        {
            label: 'Expense / KM',
            value: `₹${formatMoney(expensePerKm)}`,
            toneClass: 'is-expense',
        },
        {
            label: 'Profit / KM',
            value: `₹${formatMoney(profitPerKm)}`,
            toneClass: profitPerKm >= 0 ? 'is-profit' : 'is-loss',
        },
    ];
});

const expenseBreakdown = computed(() => {
    const totals = new Map();

    for (const expense of props.expenses ?? []) {
        const type = String(expense.expense_type ?? 'OTHER');
        const otherType = expense.other_type ?? '';
        const key = type === 'OTHER' && otherType ? `OTHER:${otherType}` : type;
        const label = expenseTypeLabel(type, otherType);
        const amount = Number(expense.amount ?? 0);
        const existing = totals.get(key);

        if (existing) {
            existing.amount += amount;
        } else {
            totals.set(key, { key, label, amount });
        }
    }

    const rows = [...totals.values()]
        .filter((row) => row.amount > 0)
        .sort((a, b) => b.amount - a.amount);

    const breakdownTotal = rows.reduce((sum, row) => sum + row.amount, 0);

    return rows.map((row) => {
        const sharePct = breakdownTotal > 0 ? Math.min(100, (row.amount / breakdownTotal) * 100) : 0;

        return {
            ...row,
            sharePct,
            shareLabel: `${sharePct.toFixed(0)}%`,
        };
    });
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<style scoped>
.trip-financial-summary {
    overflow: hidden;
    border-color: #d8e9df;
}

.trip-financial-summary--loss {
    border-color: #fecaca;
}

.trip-financial-summary__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #d8e9df;
    background: linear-gradient(135deg, #d1e7dd 0%, #f0fdf4 55%, #f8f9fa 100%);
}

.trip-financial-summary--loss .trip-financial-summary__header {
    border-bottom-color: #fecaca;
    background: linear-gradient(135deg, #fee2e2 0%, #fff7ed 55%, #f8f9fa 100%);
}

.trip-financial-summary__title {
    display: block;
    font-size: 0.95rem;
    color: #0f172a;
}

.trip-financial-summary__subtitle {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.72rem;
    color: #64748b;
}

.trip-financial-summary__badge {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    padding: 0.35rem 0.7rem;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 800;
    border: 1px solid transparent;
    white-space: nowrap;
}

.trip-financial-summary__badge.is-profit {
    color: #166534;
    background: #dcfce7;
    border-color: #86efac;
}

.trip-financial-summary__badge.is-loss {
    color: #991b1b;
    background: #fee2e2;
    border-color: #fca5a5;
}

.trip-financial-summary__body {
    padding: 0.9rem 1rem 1rem;
    background: #f6fbf8;
}

.trip-financial-summary--loss .trip-financial-summary__body {
    background: #fffaf8;
}

.trip-financial-summary__stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(5.5rem, 1fr));
    gap: 0.55rem;
    margin-bottom: 0.85rem;
}

.trip-financial-summary__stat {
    min-width: 0;
    padding: 0.35rem 0.4rem;
    border-radius: 10px;
    border: 1px solid #d8e9df;
    background: #fff;
}

.trip-financial-summary__stat--result {
    border-color: #86efac;
    background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%);
}

.trip-financial-summary--loss .trip-financial-summary__stat--result {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
}

.trip-financial-summary__stat-label {
    display: block;
    margin-bottom: 0.2rem;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: #64748b;
}

.trip-financial-summary__stat-value {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    word-break: break-word;
}

.trip-financial-summary__stat-value--expense {
    color: #b45309;
}

.trip-financial-summary__stat-value.is-profit {
    color: #15803d;
}

.trip-financial-summary__stat-value.is-loss {
    color: #dc2626;
}

.trip-financial-summary__bar {
    position: relative;
    height: 10px;
    margin-bottom: 0.45rem;
    border-radius: 99px;
    background: #e9ecef;
    overflow: hidden;
}

.trip-financial-summary__bar-freight,
.trip-financial-summary__bar-expense {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    border-radius: 99px;
    transition: width 0.25s ease;
}

.trip-financial-summary__bar-freight {
    background: #86efac;
    z-index: 1;
}

.trip-financial-summary__bar-expense {
    background: rgba(245, 158, 11, 0.85);
    z-index: 2;
}

.trip-financial-summary__legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.55rem 0.85rem;
    margin-bottom: 0.85rem;
    font-size: 0.72rem;
    color: #64748b;
}

.trip-financial-summary__dot {
    display: inline-block;
    width: 0.55rem;
    height: 0.55rem;
    margin-right: 0.3rem;
    border-radius: 50%;
    vertical-align: 0.05rem;
}

.trip-financial-summary__dot--freight {
    background: #22c55e;
}

.trip-financial-summary__dot--expense {
    background: #f59e0b;
}

.trip-financial-summary__margin {
    margin-left: auto;
    font-weight: 700;
    color: #0f172a;
}

.trip-financial-summary__per-km {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
    margin-bottom: 0.85rem;
    padding: 0.55rem 0.6rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}

.trip-financial-summary__per-km-item {
    min-width: 0;
}

.trip-financial-summary__per-km-label {
    display: block;
    margin-bottom: 0.1rem;
    font-size: 0.64rem;
    font-weight: 700;
    color: #64748b;
}

.trip-financial-summary__per-km-value {
    display: block;
    font-size: 0.8rem;
    font-weight: 800;
    color: #0f172a;
}

.trip-financial-summary__per-km-value.is-expense {
    color: #b45309;
}

.trip-financial-summary__per-km-value.is-profit {
    color: #15803d;
}

.trip-financial-summary__per-km-value.is-loss {
    color: #dc2626;
}

.trip-financial-summary__breakdown-title {
    margin-bottom: 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: none;
    letter-spacing: normal;
}

.trip-financial-summary__breakdown-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.55rem;
}

.trip-financial-summary__breakdown-row {
    min-width: 0;
}

.trip-financial-summary__breakdown-meta {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.trip-financial-summary__breakdown-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #0f172a;
}

.trip-financial-summary__breakdown-amount {
    font-size: 0.75rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}

.trip-financial-summary__breakdown-track {
    height: 6px;
    border-radius: 99px;
    background: #e2e8f0;
    overflow: hidden;
}

.trip-financial-summary__breakdown-fill {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #f59e0b, #fb923c);
}

@media (max-width: 575.98px) {
    .trip-financial-summary__stats,
    .trip-financial-summary__per-km {
        grid-template-columns: 1fr;
    }

    .trip-financial-summary__margin {
        margin-left: 0;
        width: 100%;
    }
}
</style>
