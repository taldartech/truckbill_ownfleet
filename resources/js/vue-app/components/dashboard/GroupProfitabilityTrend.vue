<template>
    <section class="group-panel group-profit">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-chart-column me-2"></i>
                    Profitability &amp; Business Trend
                </h5>
                <p class="group-panel__subtitle">Trip revenue vs direct trip cost, by trip start month</p>
            </div>
            <div class="group-profit__controls">
                <div class="group-tabs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="group-tabs__tab"
                        :class="{ 'group-tabs__tab--active': activeTab === tab.id }"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <div class="group-tabs">
                    <button
                        v-for="option in TREND_MONTH_OPTIONS"
                        :key="option"
                        type="button"
                        class="group-tabs__tab"
                        :class="{ 'group-tabs__tab--active': trendMonths === option }"
                        @click="$emit('update:trendMonths', option)"
                    >
                        {{ option }}M
                    </button>
                </div>
            </div>
        </header>

        <div class="group-profit__summary">
            <span class="group-profit__summary-title">Last {{ trendMonths }} months</span>
            <span v-for="item in summaryItems" :key="item.key" class="group-profit__summary-item" :title="item.title">
                <i class="group-profit__legend-dot" :style="{ background: item.color }"></i>
                {{ item.label }}
                <strong>{{ item.value }}</strong>
            </span>
        </div>

        <div v-if="!hasData" class="group-panel__empty">
            <i class="fas fa-chart-simple"></i>
            No trips recorded in the last {{ trendMonths }} months yet.
        </div>

        <template v-else>
            <div class="group-profit__chart">
                <div class="group-profit__axis">
                    <span>{{ formatValue(scaleMax) }}</span>
                    <span>{{ formatValue(scaleMax / 2) }}</span>
                    <span>0</span>
                </div>
                <div class="group-profit__plot">
                    <div class="group-profit__gridline group-profit__gridline--top"></div>
                    <div class="group-profit__gridline group-profit__gridline--mid"></div>
                    <div v-for="month in months" :key="month.month" class="group-profit__column">
                        <div class="group-profit__bars">
                            <div
                                v-for="series in activeSeries"
                                :key="series.key"
                                class="group-profit__bar"
                                :class="{ 'group-profit__bar--wide': activeSeries.length === 1 }"
                                :style="{
                                    height: barHeight(month[series.key]),
                                    background: barColor(series, month[series.key]),
                                }"
                                :title="`${monthLabel(month.month, true)} ${series.label}: ${formatTooltip(month[series.key])}`"
                            ></div>
                        </div>
                        <span class="group-profit__label">{{ monthLabel(month.month) }}</span>
                    </div>
                </div>
            </div>

            <div class="group-profit__legend">
                <span v-for="series in activeSeries" :key="series.key">
                    <i class="group-profit__legend-dot" :style="{ background: series.color }"></i>{{ series.label }}
                </span>
                <span v-if="activeTab === 'money'">
                    <i class="group-profit__legend-dot" style="background: #ef4444"></i>Loss
                </span>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatDynamicMoney, formatMoney, formatMoneyCompact } from '../../utils/formatMoney';
import './groupDashboard.css';

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const TREND_MONTH_OPTIONS = [6, 12];

const props = defineProps({
    months: {
        type: Array,
        default: () => [],
    },
    trendMonths: {
        type: Number,
        default: 12,
    },
});

defineEmits(['update:trendMonths']);

const tabs = [
    { id: 'money', label: 'Revenue vs Cost vs Profit' },
    { id: 'trips', label: 'Trips' },
    { id: 'utilization', label: 'Utilization' },
];

const SERIES = {
    money: [
        { key: 'revenue', label: 'Revenue', color: '#2fb36f' },
        { key: 'cost', label: 'Direct cost', color: '#fb923c' },
        { key: 'profit', label: 'Profit', color: '#0f766e' },
    ],
    trips: [{ key: 'trips', label: 'Trips', color: '#0284c7' }],
    utilization: [{ key: 'utilization', label: 'Fleet utilization', color: '#6366f1' }],
};

const activeTab = ref('money');

const activeSeries = computed(() => SERIES[activeTab.value]);

const hasData = computed(() => props.months.some((month) => Number(month.trips ?? 0) > 0));

const scaleMax = computed(() => {
    if (activeTab.value === 'utilization') {
        return 100;
    }

    const values = props.months.flatMap((month) =>
        activeSeries.value.map((series) => Math.abs(Number(month[series.key] ?? 0))),
    );

    return Math.max(...values, 1);
});

const summaryItems = computed(() => {
    const totals = props.months.reduce(
        (sum, month) => ({
            revenue: sum.revenue + Number(month.revenue ?? 0),
            cost: sum.cost + Number(month.cost ?? 0),
            profit: sum.profit + Number(month.profit ?? 0),
            trips: sum.trips + Number(month.trips ?? 0),
        }),
        { revenue: 0, cost: 0, profit: 0, trips: 0 },
    );
    const period = `last ${props.trendMonths} months`;

    return [
        { key: 'revenue', label: 'Revenue', color: '#2fb36f', value: formatDynamicMoney(totals.revenue), title: `${formatMoney(totals.revenue)} · ${period}` },
        { key: 'cost', label: 'Direct cost', color: '#fb923c', value: formatDynamicMoney(totals.cost), title: `${formatMoney(totals.cost)} · ${period}` },
        { key: 'profit', label: 'Profit', color: '#0f766e', value: formatDynamicMoney(totals.profit), title: `${formatMoney(totals.profit)} · ${period}` },
        {
            key: 'margin',
            label: 'Margin',
            color: '#0284c7',
            value: totals.revenue !== 0 ? `${((totals.profit / totals.revenue) * 100).toFixed(1)}%` : '—',
            title: period,
        },
        { key: 'trips', label: 'Trips', color: '#6366f1', value: totals.trips.toLocaleString('en-IN'), title: period },
    ];
});

function barHeight(value) {
    const amount = Number(value ?? 0);
    const ratio = Math.abs(amount) / scaleMax.value;

    return `${Math.max(Math.min(ratio, 1) * 100, amount !== 0 ? 2 : 0)}%`;
}

function barColor(series, value) {
    if (series.key === 'profit' && Number(value ?? 0) < 0) {
        return '#ef4444';
    }

    return series.color;
}

function formatValue(value) {
    if (activeTab.value === 'utilization') {
        return `${Math.round(value)}%`;
    }

    if (activeTab.value === 'trips') {
        return Math.round(Number(value ?? 0)).toLocaleString('en-IN');
    }

    return formatMoneyCompact(value);
}

function formatTooltip(value) {
    if (activeTab.value === 'utilization') {
        return `${Number(value ?? 0)}%`;
    }

    if (activeTab.value === 'trips') {
        return `${Number(value ?? 0)} trip(s)`;
    }

    return formatMoney(value);
}

function monthLabel(monthKey, withYear = false) {
    const [year, month] = String(monthKey).split('-');
    const name = MONTH_NAMES[Number(month) - 1] ?? monthKey;

    if (withYear || month === '01') {
        return `${name} ${withYear ? year : `'${year.slice(2)}`}`;
    }

    return name;
}
</script>

<style scoped>
.group-profit__controls {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.group-profit__summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem 1.1rem;
    margin-bottom: 0.9rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.65rem;
    background: #f8fafc;
    font-size: 0.74rem;
    color: #64748b;
}

.group-profit__summary-title {
    font-weight: 700;
    color: #475569;
}

.group-profit__summary-item {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

.group-profit__summary-item strong {
    margin-left: 0.3rem;
    font-size: 0.85rem;
    color: #0f172a;
}

.group-profit__chart {
    display: flex;
    gap: 0.6rem;
    height: 190px;
}

.group-profit__axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding-bottom: 1.4rem;
    font-size: 0.66rem;
    color: #94a3b8;
    text-align: right;
    min-width: 3rem;
}

.group-profit__plot {
    position: relative;
    display: flex;
    flex: 1;
    align-items: stretch;
    gap: 0.35rem;
    padding-bottom: 1.4rem;
    min-width: 0;
}

.group-profit__gridline {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 1px dashed #e2e8f0;
    pointer-events: none;
}

.group-profit__gridline--top {
    top: 0;
}

.group-profit__gridline--mid {
    top: calc((100% - 1.4rem) / 2);
}

.group-profit__column {
    position: relative;
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    min-width: 0;
}

.group-profit__bars {
    display: flex;
    flex: 1;
    align-items: flex-end;
    justify-content: center;
    gap: 2px;
    width: 100%;
    border-bottom: 1px solid #e2e8f0;
}

.group-profit__bar {
    width: 26%;
    max-width: 14px;
    border-radius: 4px 4px 0 0;
    transition: height 0.35s ease;
}

.group-profit__bar--wide {
    width: 55%;
    max-width: 28px;
}

.group-profit__label {
    position: absolute;
    bottom: -1.3rem;
    font-size: 0.66rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.group-profit__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
    margin-top: 0.6rem;
    font-size: 0.72rem;
    color: #64748b;
}

.group-profit__legend-dot {
    display: inline-block;
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 2px;
    margin-right: 0.35rem;
}

@media (max-width: 767.98px) {
    .group-profit__label {
        font-size: 0.58rem;
    }
}
</style>
