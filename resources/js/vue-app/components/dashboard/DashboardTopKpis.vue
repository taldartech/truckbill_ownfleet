<template>
    <div class="dashboard-kpis">
        <div class="dashboard-kpis__grid">
            <!-- 1. Profit -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--clickable"
                :class="profit >= 0 ? 'dashboard-kpi-card--emerald' : 'dashboard-kpi-card--danger'"
                role="button"
                tabindex="0"
                title="Click to view Profit Margin chart"
                @click="$emit('select-tab', 'financials')"
                @keydown.enter="$emit('select-tab', 'financials')"
            >
                <div class="dashboard-kpi-card__head">
                    <div
                        class="dashboard-kpi-card__icon"
                        :class="profit >= 0 ? 'icon-emerald' : 'icon-danger'"
                    >
                        <i class="fas fa-wallet"></i>
                    </div>
                    <span
                        class="dashboard-kpi-card__tag"
                        :class="profit >= 0 ? 'text-success bg-success-subtle' : 'text-danger bg-danger-subtle'"
                    >
                        {{ profit >= 0 ? 'Net Profit' : 'Net Loss' }}
                    </span>
                </div>
                <div class="dashboard-kpi-card__body">
                    <div
                        class="dashboard-kpi-card__value"
                        :class="profit >= 0 ? 'text-emerald' : 'text-danger'"
                    >
                        {{ formattedProfit }}
                    </div>
                    <div class="dashboard-kpi-card__label">Profit</div>
                    <div class="dashboard-kpi-card__hint">Revenue − expenses</div>
                </div>
                <div
                    class="dashboard-kpi-card__bar"
                    :class="profit >= 0 ? 'bar-emerald' : 'bar-danger'"
                ></div>
            </div>

            <!-- 2. Outstanding -->
            <component
                :is="invoicesRoute ? 'RouterLink' : 'div'"
                :to="invoicesRoute"
                class="dashboard-kpi-card dashboard-kpi-card--warning"
                :class="{ 'dashboard-kpi-card--clickable': Boolean(invoicesRoute) }"
                title="View open invoices"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__icon icon-warning">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <span class="dashboard-kpi-card__tag text-warning bg-warning-subtle">Receivables</span>
                </div>
                <div class="dashboard-kpi-card__body">
                    <div class="dashboard-kpi-card__value text-warning">{{ formattedOutstanding }}</div>
                    <div class="dashboard-kpi-card__label">Outstanding</div>
                    <div class="dashboard-kpi-card__hint">Unpaid invoice balance</div>
                </div>
                <div class="dashboard-kpi-card__bar bar-warning"></div>
            </component>

            <!-- 3. Revenue -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--success dashboard-kpi-card--clickable"
                role="button"
                tabindex="0"
                title="Click to view Financials chart"
                @click="$emit('select-tab', 'financials')"
                @keydown.enter="$emit('select-tab', 'financials')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__icon icon-success">
                        <i class="fas fa-arrow-trend-up"></i>
                    </div>
                    <span class="dashboard-kpi-card__tag text-success bg-success-subtle">Revenue</span>
                </div>
                <div class="dashboard-kpi-card__body">
                    <div class="dashboard-kpi-card__value text-success">{{ formattedRevenue }}</div>
                    <div class="dashboard-kpi-card__label">Revenue</div>
                    <div class="dashboard-kpi-card__hint">Freight &amp; custom in period</div>
                </div>
                <div class="dashboard-kpi-card__bar bar-success"></div>
            </div>

            <!-- 4. Fleet Utilization -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--info dashboard-kpi-card--clickable"
                role="button"
                tabindex="0"
                title="Click to view Fleet Utilization Report"
                @click="$emit('select-tab', 'fleet')"
                @keydown.enter="$emit('select-tab', 'fleet')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__icon icon-info">
                        <i class="fas fa-gauge-high"></i>
                    </div>
                    <span
                        class="dashboard-kpi-card__tag"
                        :class="utilizationBadgeClass"
                    >
                        {{ utilizationRate >= 70 ? 'Optimal' : (utilizationRate >= 40 ? 'Moderate' : 'Low') }}
                    </span>
                </div>
                <div class="dashboard-kpi-card__body">
                    <div class="dashboard-kpi-card__value" :class="utilizationTextClass">
                        {{ utilizationRate.toFixed(0) }}%
                    </div>
                    <div class="dashboard-kpi-card__label">Fleet Utilization</div>
                    <div class="dashboard-kpi-card__hint">Running vs available days</div>
                </div>
                <div class="dashboard-kpi-card__bar-wrap">
                    <div
                        class="dashboard-kpi-card__bar"
                        :class="utilizationBarClass"
                        :style="{ width: `${Math.min(Math.max(utilizationRate, 0), 100)}%` }"
                    ></div>
                </div>
            </div>

            <!-- 5. Idle -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--warning dashboard-kpi-card--clickable"
                role="button"
                tabindex="0"
                title="Click to view idle trucks breakdown"
                @click="$emit('select-tab', 'fleet')"
                @keydown.enter="$emit('select-tab', 'fleet')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__icon icon-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <span class="dashboard-kpi-card__tag text-warning bg-warning-subtle">Idle</span>
                </div>
                <div class="dashboard-kpi-card__body">
                    <div class="dashboard-kpi-card__value text-warning">{{ idle }}</div>
                    <div class="dashboard-kpi-card__label">Idle</div>
                    <div class="dashboard-kpi-card__hint">Not used for 7+ days</div>
                </div>
                <div class="dashboard-kpi-card__bar bar-warning"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatMoney } from '../../utils/formatMoney';

const props = defineProps({
    kpiStats: {
        type: Object,
        default: () => ({}),
    },
    fleetStatusStats: {
        type: Object,
        default: () => ({}),
    },
    tripStats: {
        type: Object,
        default: () => ({}),
    },
    fleetStats: {
        type: Object,
        default: () => ({}),
    },
    canViewTrips: {
        type: Boolean,
        default: true,
    },
    canViewFleet: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['select-tab']);

const invoicesRoute = computed(() => ({ name: 'invoices.index' }));

// 1. Profit
const revenue = computed(() => {
    return Number(
        props.kpiStats?.revenue ??
        props.tripStats?.total_freight_amount ??
        0,
    );
});

const cost = computed(() => {
    return Number(
        props.kpiStats?.cost ??
        props.tripStats?.total_expense_amount ??
        0,
    );
});

const profit = computed(() => {
    return Number(
        props.kpiStats?.profit ??
        props.tripStats?.profit_amount ??
        (revenue.value - cost.value),
    );
});

// 2. Outstanding
const outstanding = computed(() => {
    return Number(props.kpiStats?.outstanding ?? 0);
});

// 3. Fleet Utilization
const utilizationRate = computed(() => {
    return Number(
        props.kpiStats?.fleet_utilization ??
        props.fleetStatusStats?.utilization?.utilization_rate ??
        0,
    );
});

// 4. Idle
const idle = computed(() => {
    return Number(
        props.kpiStats?.idle ??
        props.fleetStatusStats?.overview?.idle ??
        0,
    );
});

// Formatted strings
const formattedRevenue = computed(() => formatMoney(revenue.value));
const formattedProfit = computed(() => formatMoney(profit.value));
const formattedOutstanding = computed(() => formatMoney(outstanding.value));

// Utilization styling helpers
const utilizationTextClass = computed(() => {
    if (utilizationRate.value >= 70) return 'text-success';
    if (utilizationRate.value >= 40) return 'text-primary';
    return 'text-warning';
});

const utilizationBarClass = computed(() => {
    if (utilizationRate.value >= 70) return 'bg-success';
    if (utilizationRate.value >= 40) return 'bg-primary';
    return 'bg-warning';
});

const utilizationBadgeClass = computed(() => {
    if (utilizationRate.value >= 70) return 'text-success bg-success-subtle';
    if (utilizationRate.value >= 40) return 'text-primary bg-primary-subtle';
    return 'text-warning bg-warning-subtle';
});
</script>

<style scoped>
.dashboard-kpis {
    width: 100%;
    margin-bottom: 0.25rem;
}

.dashboard-kpis__grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.85rem;
}

.dashboard-kpi-card {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.45rem;
    padding: 0.95rem 1.1rem;
    border-radius: 0.95rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    text-decoration: none;
    color: inherit;
    overflow: hidden;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.dashboard-kpi-card--clickable {
    cursor: pointer;
}

.dashboard-kpi-card--clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
    border-color: #cbd5e1;
    color: inherit;
}

.dashboard-kpi-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.dashboard-kpi-card__icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.icon-success {
    background: #ecfdf5;
    color: #10b981;
}

.icon-warning {
    background: #fffbeb;
    color: #f59e0b;
}

.icon-danger {
    background: #fef2f2;
    color: #ef4444;
}

.icon-emerald {
    background: #ecfdf5;
    color: #059669;
}

.icon-info {
    background: #f0f9ff;
    color: #0284c7;
}

.text-emerald {
    color: #059669 !important;
}

.dashboard-kpi-card__tag {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 6px;
    background: #f8fafc;
    color: #64748b;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.dashboard-kpi-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.dashboard-kpi-card__value {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.15;
    color: #0f172a;
    letter-spacing: -0.025em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-kpi-card__label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.dashboard-kpi-card__hint {
    font-size: 0.7rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-kpi-card__bar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    opacity: 0.85;
}

.dashboard-kpi-card__bar-wrap {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #e2e8f0;
}

.bar-success {
    background: #10b981;
}

.bar-warning {
    background: #f59e0b;
}

.bar-danger {
    background: #ef4444;
}

.bar-emerald {
    background: #059669;
}

@media (max-width: 1199.98px) {
    .dashboard-kpis__grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .dashboard-kpis__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-kpi-card__value {
        font-size: 1.35rem;
    }
}

@media (max-width: 479.98px) {
    .dashboard-kpis__grid {
        grid-template-columns: 1fr;
    }
}
</style>
