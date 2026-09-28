<template>
    <div class="analytics-charts">
        <header class="analytics-charts__header">
            <h5 class="analytics-charts__title">
                <i class="fas fa-chart-pie me-2 text-brand-primary"></i>
                Visual Analytics &amp; Insights
            </h5>
            <div class="analytics-charts__tabs">
                <button
                    v-for="tab in analyticsTabs"
                    :key="tab.id"
                    class="analytics-charts__tab-btn"
                    :class="{ 'analytics-charts__tab-btn--active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>
        </header>

        <!-- Trips & Financials Tab -->
        <div v-if="activeTab === 'financials'" class="analytics-charts__content grid-3">
            <!-- 1. Trips by Status -->
            <div class="analytics-card flex-col-center">
                <h6 class="analytics-card__title">Trips by Status</h6>
                <p v-if="periodLabel" class="analytics-card__hint">Date: {{ periodLabel }}</p>
                <div class="trip-status-grid">
                    <div
                        v-for="item in tripStatusItems"
                        :key="item.key"
                        class="trip-status-card"
                    >
                        <span class="trip-status-card__value">{{ item.count }}</span>
                        <span class="trip-status-card__label">{{ item.label }}</span>
                        <div class="trip-status-card__bar-wrap">
                            <div
                                class="trip-status-card__bar"
                                :class="item.barClass"
                                :style="{ width: `${item.pct}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="analytics-card__footer-info">
                    <span>{{ tripTotalCount }} total</span>
                    <span>{{ tripCompletionRate.toFixed(0) }}% completed</span>
                </div>
            </div>

            <!-- 2. Trip Profit -->
            <div class="analytics-card trip-profit-card">
                <p v-if="periodLabel" class="analytics-card__hint analytics-card__hint--top">Date: {{ periodLabel }}</p>
                <div class="trip-profit-card__hero">
                    <div class="financial-flow__label-row">
                        <span class="financial-flow__lbl">
                            <i class="fas fa-wallet text-brand-primary me-1"></i>
                            Trip Profit
                        </span>
                        <span
                            class="trip-profit-card__amount"
                            :class="{ 'trip-profit-card__amount--loss': profitAmount < 0 }"
                        >
                            {{ formatCurrency(profitAmount) }}
                        </span>
                    </div>
                </div>

                <div class="financial-flow">
                    <div class="financial-flow__item">
                        <div class="financial-flow__label-row">
                            <span class="financial-flow__lbl">
                                <i class="fas fa-arrow-trend-up text-success me-1"></i>
                                Total Freight
                            </span>
                            <span class="financial-flow__val">{{ formatCurrency(totalFreight) }}</span>
                        </div>
                        <div class="financial-flow__bar-wrap">
                            <div
                                class="financial-flow__bar bg-success"
                                :style="{ width: `${freightBarPct}%` }"
                            ></div>
                        </div>
                    </div>

                    <div class="financial-flow__item">
                        <div class="financial-flow__label-row">
                            <span class="financial-flow__lbl">
                                <i class="fas fa-arrow-trend-down text-danger me-1"></i>
                                Total Expenses
                            </span>
                            <span class="financial-flow__val">{{ formatCurrency(totalExpenses) }}</span>
                        </div>
                        <div class="financial-flow__bar-wrap">
                            <div
                                class="financial-flow__bar bg-danger"
                                :style="{ width: `${expensePct}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <p class="trip-profit-card__hint">
                    Period uses the trip start date. Includes non-cancelled trips. Freight includes custom amount. Profit = freight − trip expenses.
                </p>
            </div>

            <!-- 3. Net Profit Margin -->
            <div class="analytics-card profit-margin-card">
                <h6 class="analytics-card__title">Net Profit Margin</h6>
                <p v-if="periodLabel" class="analytics-card__hint">Date: {{ periodLabel }}</p>
                <div class="profit-margin-gauge">
                    <svg viewBox="0 0 100 60" class="profit-margin-gauge__svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="profitGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#2fb36f" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#2fb36f" stop-opacity="1" />
                            </linearGradient>
                            <linearGradient id="lossGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#ef4444" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#ef4444" stop-opacity="1" />
                            </linearGradient>
                        </defs>
                        <path
                            d="M 8 52 A 42 42 0 0 1 92 52"
                            fill="none"
                            stroke="#e2e8f0"
                            stroke-width="9"
                            stroke-linecap="round"
                        />
                        <path
                            d="M 8 52 A 42 42 0 0 1 92 52"
                            fill="none"
                            :stroke="profitMargin >= 0 ? 'url(#profitGrad)' : 'url(#lossGrad)'"
                            stroke-width="9"
                            stroke-linecap="round"
                            :stroke-dasharray="radialCircumference"
                            :stroke-dashoffset="radialDashoffset"
                            class="gauge-fill-arc"
                        />
                    </svg>
                    <div class="profit-margin-gauge__content">
                        <span
                            class="profit-margin-gauge__value"
                            :class="profitMargin >= 0 ? 'profit-margin-gauge__value--profit' : 'profit-margin-gauge__value--loss'"
                        >
                            {{ profitMarginDisplay }}%
                        </span>
                        <span
                            class="profit-margin-gauge__label"
                            :class="profitMargin >= 0 ? 'profit-margin-gauge__label--profit' : 'profit-margin-gauge__label--loss'"
                        >
                            {{ profitMargin >= 0 ? 'Profit Margin' : 'Loss Margin' }}
                        </span>
                        <span v-if="gaugeValueNeedsScaling" class="profit-margin-gauge__note">
                            Gauge capped at 100%
                        </span>
                    </div>
                </div>
                <div class="profit-margin-footer">
                    <div class="profit-margin-footer__item">
                        <span class="profit-margin-footer__label">Freight</span>
                        <span class="profit-margin-footer__value">{{ formatCurrency(totalFreight) }}</span>
                    </div>
                    <div class="profit-margin-footer__item">
                        <span class="profit-margin-footer__label">Profit</span>
                        <span
                            class="profit-margin-footer__value"
                            :class="{ 'profit-margin-footer__value--loss': profitAmount < 0 }"
                        >
                            {{ formatCurrency(profitAmount) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tyres & Financials Tab -->
        <div v-if="activeTab === 'tyres'" class="analytics-charts__content grid-3">
            <div class="analytics-card flex-col-center">
                <h6 class="analytics-card__title">Tyres by Status</h6>
                <p class="analytics-card__hint">Current fleet inventory</p>
                <div class="trip-status-grid">
                    <div
                        v-for="item in tyreStatusItems"
                        :key="item.key"
                        class="trip-status-card"
                    >
                        <span class="trip-status-card__value">{{ item.count }}</span>
                        <span class="trip-status-card__label">{{ item.label }}</span>
                        <div class="trip-status-card__bar-wrap">
                            <div
                                class="trip-status-card__bar"
                                :class="item.barClass"
                                :style="{ width: `${item.pct}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="analytics-card__footer-info">
                    <span>{{ tyreTotalCount }} total</span>
                    <span>{{ tyreInstalledCount }} installed</span>
                </div>
            </div>

           <!-- <div v-if="tyreAlertItems.length" class="analytics-card flex-col-center">
                <h6 class="analytics-card__title">Tyre Alerts</h6>
                <div class="trip-status-grid">
                    <div
                        v-for="item in tyreAlertItems"
                        :key="item.key"
                        class="trip-status-card"
                    >
                        <span class="trip-status-card__value">{{ item.count }}</span>
                        <span class="trip-status-card__label">{{ item.label }}</span>
                    </div>
                </div>
            </div>-->

            <div class="analytics-card analytics-card--span-2 flex-col-center">
                <h6 class="analytics-card__title">Tyre Financial Distribution</h6>
                <p v-if="periodLabel" class="analytics-card__hint">Date: {{ periodLabel }}</p>
                <div class="column-chart-container">
                    <div class="column-chart">
                        <div class="column-group">
                            <div class="column-bar-container">
                                <div
                                    class="column-bar bg-purple-grad"
                                    :style="{ height: `${tyrePurchaseHeight}%` }"
                                    @mouseover="showTooltip('purchases', $event)"
                                    @mouseleave="hideTooltip"
                                >
                                    <span class="column-value-label">{{ formatCurrency(tyrePurchasesCost) }}</span>
                                </div>
                            </div>
                            <span class="column-label">Purchases</span>
                        </div>
                        <div class="column-group">
                            <div class="column-bar-container">
                                <div
                                    class="column-bar bg-amber-grad"
                                    :style="{ height: `${tyreMaintenanceHeight}%` }"
                                    @mouseover="showTooltip('maintenance', $event)"
                                    @mouseleave="hideTooltip"
                                >
                                    <span class="column-value-label">{{ formatCurrency(tyreExpensesTotal) }}</span>
                                </div>
                            </div>
                            <span class="column-label">Repair &amp; Maintenance</span>
                        </div>
                        <div class="column-group">
                            <div class="column-bar-container">
                                <div
                                    class="column-bar bg-emerald-grad"
                                    :style="{ height: `${tyreRecoveryHeight}%` }"
                                    @mouseover="showTooltip('recovery', $event)"
                                    @mouseleave="hideTooltip"
                                >
                                    <span class="column-value-label">{{ formatCurrency(tyreScrapRecovery) }}</span>
                                </div>
                            </div>
                            <span class="column-label">Scrap Recovery</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Tooltip -->
        <div
            v-if="tooltip.show"
            class="chart-tooltip"
            :style="{ top: `${tooltip.y}px`, left: `${tooltip.x}px` }"
        >
            <div class="chart-tooltip__title">{{ tooltip.title }}</div>
            <div class="chart-tooltip__value">{{ tooltip.value }}</div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { formatMoney } from '../../utils/formatMoney';
import { formatDateRangeLabel } from '../../utils/dateRangePresets';

const props = defineProps({
    tripStats: {
        type: Object,
        default: () => ({}),
    },
    tyreStats: {
        type: Object,
        default: () => ({}),
    },
    periodFromDate: {
        type: String,
        default: '',
    },
    periodToDate: {
        type: String,
        default: '',
    },
    canViewTrips: {
        type: Boolean,
        default: true,
    },
    canViewTyres: {
        type: Boolean,
        default: false,
    },
});

const periodLabel = computed(() =>
    formatDateRangeLabel(props.periodFromDate, props.periodToDate),
);

const analyticsTabs = computed(() => {
    const tabs = [];
    if (props.canViewTrips) {
        tabs.push({ id: 'financials', label: 'Trips & Financials' });
    }
    if (props.canViewTyres) {
        tabs.push({ id: 'tyres', label: 'Tyres & Financials' });
    }
    return tabs;
});

const activeTab = ref(props.canViewTrips ? 'financials' : 'tyres');

// Tooltip State
const tooltip = ref({
    show: false,
    title: '',
    value: '',
    x: 0,
    y: 0,
});

function formatCurrency(value) {
    return formatMoney(value);
}

// Tab 1: Financials Computed
const totalFreight = computed(() => Number(props.tripStats?.total_freight_amount ?? 0));
const totalExpenses = computed(() => Number(props.tripStats?.total_expense_amount ?? 0));
const profitAmount = computed(() => Number(props.tripStats?.profit_amount ?? 0));

const profitMargin = computed(() => {
    if (totalFreight.value <= 0) {
        return 0;
    }
    return (profitAmount.value / totalFreight.value) * 100;
});

const expensePct = computed(() => {
    if (totalFreight.value <= 0) {
        return totalExpenses.value > 0 ? 100 : 0;
    }
    return Math.min((totalExpenses.value / totalFreight.value) * 100, 100);
});

const freightBarPct = computed(() => (totalFreight.value > 0 ? 100 : 0));

const tripTotalCount = computed(() => Number(props.tripStats?.total_count ?? 0));
const tripPendingCount = computed(() => Number(props.tripStats?.pending_count ?? props.tripStats?.created_count ?? 0));
const tripRunningCount = computed(() => Number(props.tripStats?.in_progress_count ?? 0));
const tripCompletedCount = computed(() => Number(props.tripStats?.completed_count ?? 0));
const tripCancelledCount = computed(() => Number(props.tripStats?.cancelled_count ?? 0));
const tripInvoicedCount = computed(() => Number(props.tripStats?.invoiced_count ?? 0));

function tripStatusPct(count) {
    if (tripTotalCount.value <= 0) {
        return count > 0 ? 100 : 0;
    }

    return Math.max(0, Math.min((count / tripTotalCount.value) * 100, 100));
}

const tripPendingPct = computed(() => tripStatusPct(tripPendingCount.value));
const tripRunningPct = computed(() => tripStatusPct(tripRunningCount.value));
const tripCompletedPct = computed(() => tripStatusPct(tripCompletedCount.value));
const tripCancelledPct = computed(() => tripStatusPct(tripCancelledCount.value));
const tripInvoicedPct = computed(() => tripStatusPct(tripInvoicedCount.value));

const tripCompletionRate = computed(() => {
    if (tripTotalCount.value <= 0) {
        return 0;
    }

    return ((tripCompletedCount.value + tripInvoicedCount.value) / tripTotalCount.value) * 100;
});

const tripStatusItems = computed(() => [
    {
        key: 'pending',
        label: 'Pending',
        count: tripPendingCount.value,
        pct: tripPendingPct.value,
        barClass: 'trip-status-card__bar--created',
    },
    {
        key: 'running',
        label: 'Running',
        count: tripRunningCount.value,
        pct: tripRunningPct.value,
        barClass: 'trip-status-card__bar--running',
    },
    {
        key: 'completed',
        label: 'Completed',
        count: tripCompletedCount.value,
        pct: tripCompletedPct.value,
        barClass: 'trip-status-card__bar--completed',
    },
    {
        key: 'invoiced',
        label: 'Invoiced',
        count: tripInvoicedCount.value,
        pct: tripInvoicedPct.value,
        barClass: 'trip-status-card__bar--completed',
    },
    {
        key: 'cancelled',
        label: 'Cancelled',
        count: tripCancelledCount.value,
        pct: tripCancelledPct.value,
        barClass: 'trip-status-card__bar--cancelled',
    },
]);

// Radial Gauge Config
const radialCircumference = Math.PI * 42;
const profitMarginDisplay = computed(() => {
    const value = profitMargin.value;
    const abs = Math.abs(value);

    if (abs >= 1000) {
        return `${value < 0 ? '-' : ''}${(abs / 1000).toFixed(1)}k`;
    }

    if (abs >= 100) {
        return value.toFixed(0);
    }

    return value.toFixed(1);
});
const gaugeValueNeedsScaling = computed(() => Math.abs(profitMargin.value) >= 100);
const radialDashoffset = computed(() => {
    const absMargin = Math.min(Math.abs(profitMargin.value), 100);
    return radialCircumference - (absMargin / 100) * radialCircumference;
});

// Tab 3: Tyres Computed
const tyreTotalCount = computed(() => Number(props.tyreStats?.total_count ?? 0));
const tyreInStockCount = computed(() => Number(props.tyreStats?.in_stock_count ?? 0));
const tyreInstalledCount = computed(() => Number(props.tyreStats?.installed_count ?? 0));
const tyreScrappedCount = computed(() => Number(props.tyreStats?.scrapped_count ?? 0));

const tyreAlertItems = computed(() => [
    {
        key: 'near_end_of_life',
        label: 'Near End of Life',
        count: Number(props.tyreStats?.near_end_of_life ?? 0),
    },
    {
        key: 'warranty_expiring',
        label: 'Warranty Expiring',
        count: Number(props.tyreStats?.warranty_expiring ?? 0),
    },
    {
        key: 'inspection_overdue',
        label: 'Inspection Overdue',
        count: Number(props.tyreStats?.inspection_overdue ?? 0),
    },
    {
        key: 'vehicles_empty_steer',
        label: 'Trucks Missing Steer Tyres',
        count: Number(props.tyreStats?.vehicles_empty_steer ?? 0),
    },
].filter((item) => item.count > 0));

function tyreStatusPct(count) {
    if (tyreTotalCount.value <= 0) {
        return count > 0 ? 100 : 0;
    }

    return Math.max(0, Math.min((count / tyreTotalCount.value) * 100, 100));
}

const tyreStatusItems = computed(() => [
    {
        key: 'in_stock',
        label: 'In Stock',
        count: tyreInStockCount.value,
        pct: tyreStatusPct(tyreInStockCount.value),
        barClass: 'trip-status-card__bar--running',
    },
    {
        key: 'installed',
        label: 'Installed',
        count: tyreInstalledCount.value,
        pct: tyreStatusPct(tyreInstalledCount.value),
        barClass: 'trip-status-card__bar--completed',
    },
    {
        key: 'scrapped',
        label: 'Scrapped',
        count: tyreScrappedCount.value,
        pct: tyreStatusPct(tyreScrappedCount.value),
        barClass: 'trip-status-card__bar--created',
    },
]);

const tyrePurchasesCost = computed(() => Number(props.tyreStats?.purchased_total_cost ?? 0));
const tyreExpensesTotal = computed(() => Number(props.tyreStats?.expenses_total ?? 0));
const tyreScrapRecovery = computed(() => Number(props.tyreStats?.period_scrapped_recovery_amount ?? 0));

const maxTyreAmount = computed(() => {
    const val = Math.max(tyrePurchasesCost.value, tyreExpensesTotal.value, tyreScrapRecovery.value);
    return val > 0 ? val * 1.15 : 1000; // 15% padding
});

const tyrePurchaseHeight = computed(() => {
    if (tyrePurchasesCost.value <= 0) return 0;
    const base = (tyrePurchasesCost.value / maxTyreAmount.value) * 100;
    return Math.max(base, 10);
});
const tyreMaintenanceHeight = computed(() => {
    if (tyreExpensesTotal.value <= 0) return 0;
    const base = (tyreExpensesTotal.value / maxTyreAmount.value) * 100;
    return Math.max(base, 10);
});
const tyreRecoveryHeight = computed(() => {
    if (tyreScrapRecovery.value <= 0) return 0;
    const base = (tyreScrapRecovery.value / maxTyreAmount.value) * 100;
    return Math.max(base, 10);
});

// Interactive Tooltips
function showTooltip(type, event) {
    let title = '';
    let valStr = '';

    if (type === 'purchases') {
        title = 'Tyre Purchases Cost';
        valStr = formatCurrency(tyrePurchasesCost.value) + ` (${props.tyreStats?.purchased_count ?? 0} Tyres)`;
    } else if (type === 'maintenance') {
        title = 'Repair & Maintenance';
        valStr = formatCurrency(tyreExpensesTotal.value);
    } else if (type === 'recovery') {
        title = 'Scrap Sale Recovery';
        valStr = formatCurrency(tyreScrapRecovery.value) + ` (${props.tyreStats?.period_scrapped_count ?? 0} Tyres)`;
    }

    tooltip.value = {
        show: true,
        title,
        value: valStr,
        x: event.clientX + 10,
        y: event.clientY - 45,
    };
}

function hideTooltip() {
    tooltip.value.show = false;
}
</script>

<style scoped>
.analytics-charts {
    background: #ffffff;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem 1.5rem;
    margin: 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.analytics-charts__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem 1rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px dashed var(--brand-border, #e2e8f0);
    padding-bottom: 0.9rem;
}

.analytics-charts__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
}

.analytics-charts__tabs {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.3rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 999px;
    max-width: 100%;
}

.analytics-charts__tab-btn {
    border: none;
    outline: none;
    background: transparent;
    padding: 0.4rem 0.7rem;
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.analytics-charts__tab-btn:hover {
    color: #0f172a;
}

.analytics-charts__tab-btn--active {
    background: #ffffff;
    color: var(--brand-primary-dark, #23965c);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
}

.analytics-charts__content {
    animation: fadeIn 0.3s ease;
}

.grid-3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 1rem;
}

.grid-1 {
    display: grid;
    grid-template-columns: 1fr;
}

.analytics-card--span-2 {
    grid-column: span 2;
}

@media (min-width: 1200px) {
    .analytics-charts__tab-btn {
        padding: 0.45rem 0.95rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 991.98px) {
    .grid-3 {
        grid-template-columns: 1fr;
    }

    .analytics-card--span-2 {
        grid-column: span 1;
    }

    .analytics-charts__tabs {
        justify-content: flex-start;
        width: 100%;
    }
}

/* Common Card Style */
.analytics-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 1rem;
    padding: 1.25rem;
    position: relative;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.01);
}

.flex-col-center {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.analytics-card__title {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #475569;
    text-transform: none;
    letter-spacing: normal;
}

.analytics-card__hint {
    margin: -0.65rem 0 0.85rem 0;
    font-size: 0.72rem;
    color: #94a3b8;
}

.analytics-card__hint--top {
    margin: 0 0 0.75rem 0;
}

/* Gauge styling */
.profit-margin-card {
    gap: 0.5rem;
}

.profit-margin-gauge {
    position: relative;
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    min-height: 9.5rem;
    padding: 0.25rem 0 0.5rem;
}

.profit-margin-gauge__svg {
    width: min(100%, 220px);
    height: auto;
    overflow: visible;
}

.profit-margin-gauge__content {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    padding-top: 1.35rem;
    text-align: center;
    pointer-events: none;
}

.profit-margin-gauge__value {
    font-size: clamp(1.35rem, 4vw, 1.75rem);
    line-height: 1;
    font-weight: 800;
    letter-spacing: -0.02em;
    white-space: nowrap;
}

.profit-margin-gauge__value--profit {
    color: #15803d;
}

.profit-margin-gauge__value--loss {
    color: #dc2626;
}

.profit-margin-gauge__label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: none;
    letter-spacing: normal;
}

.profit-margin-gauge__label--profit {
    color: #16a34a;
}

.profit-margin-gauge__label--loss {
    color: #ef4444;
}

.profit-margin-gauge__note {
    margin-top: 0.15rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: #94a3b8;
}

.profit-margin-footer {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    margin-top: 0.35rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 0.75rem;
}

.profit-margin-footer__item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.profit-margin-footer__label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    text-transform: none;
    letter-spacing: normal;
}

.profit-margin-footer__value {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profit-margin-footer__value--loss {
    color: #dc2626;
}

.analytics-card__chart-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0.5rem 0;
}

.analytics-card__chart-wrap--grow {
    flex: 1;
}

.gauge-fill-arc {
    transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.analytics-card__footer-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #94a3b8;
    font-weight: 600;
    margin-top: 0.5rem;
    border-top: 1px solid #f1f5f9;
    padding-top: 0.5rem;
}

/* Trip profit card */
.trip-profit-card {
    gap: 0.85rem;
}

.trip-profit-card__hero {
    padding-bottom: 0.85rem;
    border-bottom: 1px dashed #e2e8f0;
}

.trip-profit-card__amount {
    font-size: 1.35rem;
    line-height: 1.1;
    font-weight: 800;
    color: #0f172a;
    text-align: right;
    white-space: nowrap;
}

.trip-profit-card__amount--loss {
    color: #dc2626;
}

.trip-profit-card__hint {
    margin: 0;
    font-size: 0.72rem;
    color: #94a3b8;
    text-align: right;
}

/* Flow chart styling */
.financial-flow {
    display: flex;
    flex: 1;
    flex-direction: column;
    justify-content: center;
    gap: 1rem;
    width: 100%;
}

.trip-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    flex: 1;
    align-content: center;
    gap: 0.65rem;
    width: 100%;
}

.trip-status-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.7rem 0.75rem;
    border-radius: 0.75rem;
    background: #ffffff;
    border: 1px solid #edf2f7;
}

.trip-status-card__value {
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}

.trip-status-card__label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
}

.trip-status-card__bar-wrap {
    height: 0.35rem;
    margin-top: 0.25rem;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.trip-status-card__bar {
    height: 100%;
    border-radius: 999px;
    min-width: 0;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.trip-status-card__bar--created {
    background: linear-gradient(90deg, #94a3b8, #64748b);
}

.trip-status-card__bar--running {
    background: linear-gradient(90deg, #60a5fa, #3b82f6);
}

.trip-status-card__bar--completed {
    background: linear-gradient(90deg, #4ade80, #2fb36f);
}

.trip-status-card__bar--cancelled {
    background: linear-gradient(90deg, #f87171, #ef4444);
}

.financial-flow__item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.financial-flow__label-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
}

.financial-flow__lbl {
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    min-width: 0;
}

.financial-flow__val {
    font-weight: 700;
    color: #0f172a;
    text-align: right;
    white-space: nowrap;
}

.financial-flow__bar-wrap {
    height: 0.5rem;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.financial-flow__bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.bg-success {
    background: linear-gradient(90deg, #4ade80, #2fb36f);
}

.bg-danger {
    background: linear-gradient(90deg, #f87171, #ef4444);
}

.bg-primary {
    background: linear-gradient(90deg, #60a5fa, #2563eb);
}

.bg-danger-dark {
    background: linear-gradient(90deg, #ef4444, #991b1b);
}

/* Column Chart Styling (Tyres) */
.column-chart-container {
    padding: 1.5rem 0.5rem 0.5rem;
    display: flex;
    flex: 1;
    justify-content: center;
    align-items: center;
    width: 100%;
}

.column-chart-container--compact {
    padding: 0.75rem 0 0.25rem;
}

.column-chart {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2.5rem;
    width: 100%;
    max-width: 650px;
    height: 200px;
    align-items: flex-end;
    border-bottom: 2px solid #cbd5e1;
    padding-bottom: 0.25rem;
}

.column-chart--compact {
    gap: 0.65rem;
    max-width: none;
    height: 130px;
    padding-top: 1.25rem;
}

.column-chart--compact .column-bar {
    width: 100%;
    max-width: 2.25rem;
}

.column-chart--compact .column-value-label {
    top: -1.35rem;
    font-size: 0.62rem;
    padding: 0 0.2rem;
    max-width: 5.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
}

.column-chart--compact .column-label {
    font-size: 0.68rem;
}

.column-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    height: 100%;
    justify-content: flex-end;
}

.column-bar-container {
    height: calc(100% - 25px);
    width: 100%;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.column-bar {
    width: 48px;
    min-height: 8px;
    border-radius: 6px 6px 0 0;
    position: relative;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.column-bar:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
}

.column-value-label {
    position: absolute;
    top: -24px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
    background: #f8fafc;
    padding: 1px 6px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.column-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #64748b;
    text-align: center;
    line-height: 1.3;
}

.bg-purple-grad {
    background: linear-gradient(180deg, #c084fc 0%, #a855f7 100%);
}

.bg-amber-grad {
    background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%);
}

.bg-emerald-grad {
    background: linear-gradient(180deg, #34d399 0%, #059669 100%);
}

/* Tooltip design */
.chart-tooltip {
    position: fixed;
    background: rgba(15, 23, 42, 0.95);
    color: #ffffff;
    padding: 0.45rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.78rem;
    pointer-events: none;
    z-index: 9999;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(4px);
    transition: transform 0.1s ease;
}

.chart-tooltip__title {
    font-weight: 600;
    color: #94a3b8;
    margin-bottom: 0.15rem;
}

.chart-tooltip__value {
    font-weight: 700;
    color: #ffffff;
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
