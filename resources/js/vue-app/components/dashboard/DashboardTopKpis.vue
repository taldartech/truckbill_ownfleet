<template>
    <div class="dashboard-kpis">
        <div class="dashboard-kpis__grid" :class="{ 'dashboard-kpis__grid--6-items': canViewTrips }">
            <!-- 1. Profit -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--clickable"
                :class="profit >= 0 ? 'dashboard-kpi-card--profit' : 'dashboard-kpi-card--loss'"
                role="button"
                tabindex="0"
                title="Click to view Financials chart"
                @click="$emit('select-tab', 'financials')"
                @keydown.enter="$emit('select-tab', 'financials')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__lead">
                        <span class="dashboard-kpi-card__icon">
                            <i class="fas fa-sack-dollar"></i>
                        </span>
                        <span class="dashboard-kpi-card__label">Profit</span>
                    </div>
                    <span
                        class="dashboard-kpi-card__tag"
                        :class="profit >= 0 ? 'dashboard-kpi-card__tag--emerald' : 'dashboard-kpi-card__tag--rose'"
                    >
                        {{ profit >= 0 ? 'Net Profit' : 'Net Loss' }}
                    </span>
                </div>
                <div
                    class="dashboard-kpi-card__value"
                    :class="{ 'text-danger': profit < 0 }"
                    :title="formattedProfit"
                >
                    {{ formattedProfit }}
                </div>
                <div class="dashboard-kpi-card__foot">
                    <span class="dashboard-kpi-card__hint">Revenue − expenses</span>
                </div>
            </div>

            <!-- 2. Outstanding -->
            <component
                :is="invoicesRoute ? 'RouterLink' : 'div'"
                :to="invoicesRoute"
                class="dashboard-kpi-card dashboard-kpi-card--outstanding"
                :class="{ 'dashboard-kpi-card--clickable': Boolean(invoicesRoute) }"
                title="View open invoices"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__lead">
                        <span class="dashboard-kpi-card__icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </span>
                        <span class="dashboard-kpi-card__label">Outstanding</span>
                    </div>
                    <span class="dashboard-kpi-card__tag dashboard-kpi-card__tag--amber">Receivables</span>
                </div>
                <div class="dashboard-kpi-card__value" :title="formattedOutstanding">
                    {{ formattedOutstanding }}
                </div>
                <div class="dashboard-kpi-card__foot">
                    <span class="dashboard-kpi-card__hint">Unpaid invoice balance</span>
                </div>
            </component>

            <!-- 3. Revenue -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--revenue dashboard-kpi-card--clickable"
                role="button"
                tabindex="0"
                title="Click to view Financials chart"
                @click="$emit('select-tab', 'financials')"
                @keydown.enter="$emit('select-tab', 'financials')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__lead">
                        <span class="dashboard-kpi-card__icon">
                            <i class="fas fa-indian-rupee-sign"></i>
                        </span>
                        <span class="dashboard-kpi-card__label">Revenue</span>
                    </div>
                    <span class="dashboard-kpi-card__tag dashboard-kpi-card__tag--green">Revenue</span>
                </div>
                <div class="dashboard-kpi-card__value" :title="formattedRevenue">
                    {{ formattedRevenue }}
                </div>
                <div class="dashboard-kpi-card__foot">
                    <span class="dashboard-kpi-card__hint">Freight &amp; custom in period</span>
                </div>
            </div>

            <!-- 4. Fleet Utilization -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--clickable"
                :class="utilizationAccentClass"
                role="button"
                tabindex="0"
                title="Click to view Fleet Status chart"
                @click="$emit('select-tab', 'fleet')"
                @keydown.enter="$emit('select-tab', 'fleet')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__lead">
                        <span class="dashboard-kpi-card__icon">
                            <i class="fas fa-gauge-high"></i>
                        </span>
                        <span class="dashboard-kpi-card__label">Fleet Utilization</span>
                    </div>
                    <span
                        class="dashboard-kpi-card__tag"
                        :class="utilizationTagClass"
                    >
                        {{ utilizationRate >= 70 ? 'Optimal' : (utilizationRate >= 40 ? 'Moderate' : 'Low') }}
                    </span>
                </div>
                <div class="dashboard-kpi-card__value">
                    {{ utilizationRate.toFixed(0) }}%
                </div>
                <div class="dashboard-kpi-card__foot">
                    <span class="dashboard-kpi-card__hint">Running vs available days</span>
                </div>
            </div>

            <!-- 5. Idle -->
            <div
                class="dashboard-kpi-card dashboard-kpi-card--idle dashboard-kpi-card--clickable"
                role="button"
                tabindex="0"
                title="Click to view Fleet Status chart"
                @click="$emit('select-tab', 'fleet')"
                @keydown.enter="$emit('select-tab', 'fleet')"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__lead">
                        <span class="dashboard-kpi-card__icon">
                            <i class="fas fa-clock"></i>
                        </span>
                        <span class="dashboard-kpi-card__label">Idle Trucks</span>
                    </div>
                    <span class="dashboard-kpi-card__tag dashboard-kpi-card__tag--orange">7+ Days</span>
                </div>
                <div class="dashboard-kpi-card__value">
                    {{ idle }}
                </div>
                <div class="dashboard-kpi-card__foot">
                    <span class="dashboard-kpi-card__hint">Not used for 7+ days</span>
                </div>
            </div>

            <!-- 6. Fuel & Mileage -->
            <RouterLink
                v-if="canViewTrips"
                :to="{ name: 'reports.trips', query: { type: 'fuel-performance' } }"
                class="dashboard-kpi-card dashboard-kpi-card--fuel dashboard-kpi-card--clickable text-decoration-none"
                title="Click to view Fuel Performance Report"
            >
                <div class="dashboard-kpi-card__head">
                    <div class="dashboard-kpi-card__lead">
                        <span class="dashboard-kpi-card__icon">
                            <i class="fas fa-gas-pump"></i>
                        </span>
                        <span class="dashboard-kpi-card__label">Avg Mileage</span>
                    </div>
                    <span class="dashboard-kpi-card__tag dashboard-kpi-card__tag--sky">
                        {{ fuelLiters > 0 ? formatLiters(fuelLiters) : 'Fuel' }}
                    </span>
                </div>
                <div class="dashboard-kpi-card__value">
                    {{ avgMileage > 0 ? `${avgMileage.toFixed(2)} KM/L` : '—' }}
                </div>
                <div class="dashboard-kpi-card__foot">
                    <span class="dashboard-kpi-card__hint">
                        {{ fuelCost > 0 ? formatMoney(fuelCost) : 'Trip diesel logs' }}
                        <template v-if="costPerKm > 0"> (₹{{ costPerKm.toFixed(2) }}/km)</template>
                    </span>
                </div>
            </RouterLink>
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

// 5. Fuel & Mileage
const avgMileage = computed(() => Number(props.kpiStats?.avg_mileage ?? 0));
const fuelCost = computed(() => Number(props.kpiStats?.fuel_cost ?? 0));
const fuelLiters = computed(() => Number(props.kpiStats?.fuel_liters ?? 0));
const costPerKm = computed(() => Number(props.kpiStats?.fuel_cost_per_km ?? 0));

function formatLiters(val) {
    return `${Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })} L`;
}

// Formatted strings
const formattedRevenue = computed(() => formatMoney(revenue.value));
const formattedProfit = computed(() => formatMoney(profit.value));
const formattedOutstanding = computed(() => formatMoney(outstanding.value));

// Utilization styling helpers
const utilizationAccentClass = computed(() => {
    if (utilizationRate.value >= 70) return 'dashboard-kpi-card--emerald';
    if (utilizationRate.value >= 40) return 'dashboard-kpi-card--sky';
    return 'dashboard-kpi-card--amber';
});

const utilizationTagClass = computed(() => {
    if (utilizationRate.value >= 70) return 'dashboard-kpi-card__tag--emerald';
    if (utilizationRate.value >= 40) return 'dashboard-kpi-card__tag--sky';
    return 'dashboard-kpi-card__tag--amber';
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
    gap: 0.75rem;
}

.dashboard-kpis__grid--6-items {
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.dashboard-kpi-card {
    --kpi-accent: #2fb36f;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.35rem;
    padding: 0.8rem 0.95rem;
    border-radius: 1rem;
    background: #ffffff;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-top: 3px solid var(--kpi-accent);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    text-decoration: none;
    color: inherit;
    min-width: 0;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.dashboard-kpi-card--profit {
    --kpi-accent: #059669;
}

.dashboard-kpi-card--loss {
    --kpi-accent: #dc2626;
}

.dashboard-kpi-card--outstanding {
    --kpi-accent: #d97706;
}

.dashboard-kpi-card--revenue {
    --kpi-accent: #2fb36f;
}

.dashboard-kpi-card--emerald {
    --kpi-accent: #059669;
}

.dashboard-kpi-card--sky {
    --kpi-accent: #0284c7;
}

.dashboard-kpi-card--amber {
    --kpi-accent: #d97706;
}

.dashboard-kpi-card--idle {
    --kpi-accent: #f97316;
}

.dashboard-kpi-card--fuel {
    --kpi-accent: #0284c7;
}

.dashboard-kpi-card--clickable {
    cursor: pointer;
}

.dashboard-kpi-card--clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
    color: inherit;
}

.dashboard-kpi-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
}

.dashboard-kpi-card__lead {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.dashboard-kpi-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.55rem;
    height: 1.55rem;
    border-radius: 0.45rem;
    background: color-mix(in srgb, var(--kpi-accent) 12%, transparent);
    color: var(--kpi-accent);
    font-size: 0.72rem;
    flex-shrink: 0;
}

.dashboard-kpi-card__label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-kpi-card__tag {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    white-space: nowrap;
    flex-shrink: 0;
}

.dashboard-kpi-card__tag--emerald {
    background: #ecfdf5;
    color: #047857;
}

.dashboard-kpi-card__tag--rose {
    background: #fef2f2;
    color: #dc2626;
}

.dashboard-kpi-card__tag--amber {
    background: #fef3c7;
    color: #92400e;
}

.dashboard-kpi-card__tag--green {
    background: #f0fdf4;
    color: #166534;
}

.dashboard-kpi-card__tag--sky {
    background: #f0f9ff;
    color: #0369a1;
}

.dashboard-kpi-card__tag--orange {
    background: #fff7ed;
    color: #c2410c;
}

.dashboard-kpi-card__value {
    font-size: 1.38rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-kpi-card__foot {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.dashboard-kpi-card__hint {
    font-size: 0.71rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 1399.98px) {
    .dashboard-kpis__grid--6-items {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 1199.98px) {
    .dashboard-kpis__grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .dashboard-kpis__grid,
    .dashboard-kpis__grid--6-items {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-kpi-card__value {
        font-size: 1.25rem;
    }
}

@media (max-width: 479.98px) {
    .dashboard-kpis__grid,
    .dashboard-kpis__grid--6-items {
        grid-template-columns: 1fr;
    }
}
</style>
