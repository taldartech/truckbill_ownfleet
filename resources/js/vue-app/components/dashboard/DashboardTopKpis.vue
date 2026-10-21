<template>
    <div class="dashboard-kpis">
        <div
            class="row g-2 g-md-3"
            :class="canViewTrips ? 'row-cols-2 row-cols-md-3 row-cols-xl-6' : 'row-cols-2 row-cols-md-3 row-cols-xl-5'"
        >
            <!-- 1. Profit -->
            <div class="col">
                <div
                    class="dashboard-kpi-card dashboard-kpi-card--clickable h-100"
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
                        :class="[{ 'text-danger': profit < 0 }, dynamicValueClass(formattedProfit)]"
                        :title="formatMoney(profit)"
                    >
                        {{ formattedProfit }}
                    </div>
                    <div class="dashboard-kpi-card__foot">
                        <span class="dashboard-kpi-card__hint">Revenue − expenses</span>
                    </div>
                </div>
            </div>

            <!-- 2. Outstanding -->
            <div class="col">
                <component
                    :is="invoicesRoute ? 'RouterLink' : 'div'"
                    :to="invoicesRoute"
                    class="dashboard-kpi-card dashboard-kpi-card--outstanding h-100"
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
                    <div
                        class="dashboard-kpi-card__value"
                        :class="dynamicValueClass(formattedOutstanding)"
                        :title="formatMoney(outstanding)"
                    >
                        {{ formattedOutstanding }}
                    </div>
                    <div class="dashboard-kpi-card__foot">
                        <span class="dashboard-kpi-card__hint">Unpaid invoice balance</span>
                    </div>
                </component>
            </div>

            <!-- 3. Revenue -->
            <div class="col">
                <div
                    class="dashboard-kpi-card dashboard-kpi-card--revenue dashboard-kpi-card--clickable h-100"
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
                    <div
                        class="dashboard-kpi-card__value"
                        :class="dynamicValueClass(formattedRevenue)"
                        :title="formatMoney(revenue)"
                    >
                        {{ formattedRevenue }}
                    </div>
                    <div class="dashboard-kpi-card__foot">
                        <span class="dashboard-kpi-card__hint">Freight &amp; custom in period</span>
                    </div>
                </div>
            </div>

            <!-- 4. Fleet Utilization -->
            <div class="col">
                <div
                    class="dashboard-kpi-card dashboard-kpi-card--clickable h-100"
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
            </div>

            <!-- 5. Idle -->
            <div class="col">
                <div
                    class="dashboard-kpi-card dashboard-kpi-card--idle dashboard-kpi-card--clickable h-100"
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
            </div>

            <!-- 6. Fuel & Mileage -->
            <div v-if="canViewTrips" class="col">
                <RouterLink
                    :to="{ name: 'reports.trips', query: { type: 'fuel-performance' } }"
                    class="dashboard-kpi-card dashboard-kpi-card--fuel dashboard-kpi-card--clickable text-decoration-none h-100"
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
                        <span class="dashboard-kpi-card__hint" :title="fuelCost > 0 ? formatMoney(fuelCost) : ''">
                            {{ fuelCost > 0 ? formatDynamicMoney(fuelCost) : 'Trip diesel logs' }}
                            <template v-if="costPerKm > 0"> (₹{{ costPerKm.toFixed(2) }}/km)</template>
                        </span>
                    </div>
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatDynamicMoney, formatMoney } from '../../utils/formatMoney';

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

function dynamicValueClass(formattedStr) {
    const len = String(formattedStr ?? '').length;
    if (len >= 13) return 'dashboard-kpi-card__value--compact';
    if (len >= 10) return 'dashboard-kpi-card__value--medium';
    return '';
}

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

// Formatted strings with dynamic scaling for values reaching 100 Cr+
const formattedRevenue = computed(() => formatDynamicMoney(revenue.value));
const formattedProfit = computed(() => formatDynamicMoney(profit.value));
const formattedOutstanding = computed(() => formatDynamicMoney(outstanding.value));

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
    font-size: clamp(1.15rem, 1.35vw, 1.38rem);
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.dashboard-kpi-card__value--medium {
    font-size: clamp(1.05rem, 1.2vw, 1.22rem);
    letter-spacing: -0.025em;
}

.dashboard-kpi-card__value--compact {
    font-size: clamp(0.95rem, 1.05vw, 1.08rem);
    letter-spacing: -0.03em;
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
        gap: 0.45rem;
    }

    .dashboard-kpi-card {
        padding: 0.55rem 0.65rem;
        gap: 0.35rem;
        border-radius: 0.65rem;
    }

    .dashboard-kpi-card__head {
        gap: 0.25rem;
    }

    .dashboard-kpi-card__lead {
        gap: 0.3rem;
    }

    .dashboard-kpi-card__icon {
        width: 1.35rem;
        height: 1.35rem;
        font-size: 0.62rem;
        border-radius: 0.35rem;
    }

    .dashboard-kpi-card__label {
        font-size: 0.68rem;
    }

    .dashboard-kpi-card__tag {
        font-size: 0.58rem;
        padding: 0.1rem 0.32rem;
    }

    .dashboard-kpi-card__value {
        font-size: clamp(0.88rem, 3.2vw, 1.05rem);
        line-height: 1.15;
    }

    .dashboard-kpi-card__value--medium {
        font-size: clamp(0.8rem, 2.9vw, 0.95rem);
    }

    .dashboard-kpi-card__value--compact {
        font-size: clamp(0.74rem, 2.6vw, 0.88rem);
    }

    .dashboard-kpi-card__foot {
        gap: 0.2rem;
    }

    .dashboard-kpi-card__hint {
        font-size: 0.6rem;
    }
}

@media (max-width: 399.98px) {
    .dashboard-kpi-card {
        padding: 0.45rem 0.5rem;
        gap: 0.25rem;
    }

    .dashboard-kpi-card__icon {
        width: 1.2rem;
        height: 1.2rem;
        font-size: 0.58rem;
    }

    .dashboard-kpi-card__label {
        font-size: 0.62rem;
    }

    .dashboard-kpi-card__tag {
        font-size: 0.52rem;
        padding: 0.08rem 0.25rem;
    }

    .dashboard-kpi-card__value {
        font-size: 0.82rem;
    }

    .dashboard-kpi-card__hint {
        font-size: 0.56rem;
    }
}
</style>
