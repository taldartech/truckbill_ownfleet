<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-building me-2"></i>
                    Company-wise Business Snapshot
                </h5>
                <p class="group-panel__subtitle">Period figures by company</p>
            </div>
        </header>

        <div v-if="!companies.length" class="group-panel__empty">
            <i class="fas fa-building"></i>
            No companies yet.
            <RouterLink :to="{ name: 'companies.index' }" class="btn btn-sm btn-primary rounded-pill mt-2">
                Add a company
            </RouterLink>
        </div>

        <div v-else class="table-responsive group-snapshot__scroll">
            <table class="table table-sm align-middle mb-0 group-snapshot">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th class="text-end">Trips</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Fleet</th>
                        <th class="group-snapshot__utilization">Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="company in companies" :key="company.id">
                        <td>
                            <span class="group-snapshot__name">{{ company.name }}</span>
                            <span v-if="company.is_blocked" class="badge text-bg-danger group-snapshot__badge">Blocked</span>
                            <span
                                v-else-if="subscriptionBadge(company)"
                                class="badge group-snapshot__badge"
                                :class="subscriptionBadge(company).class"
                            >
                                {{ subscriptionBadge(company).label }}
                            </span>
                        </td>
                        <td class="text-end">{{ company.trips_count }}</td>
                        <td class="text-end" :title="formatMoney(company.revenue)">{{ formatMoneyCompact(company.revenue) }}</td>
                        <td class="text-end" :title="formatMoney(company.cost)">{{ formatMoneyCompact(company.cost) }}</td>
                        <td
                            class="text-end fw-semibold"
                            :class="{ 'text-danger': company.profit < 0 }"
                            :title="`${formatMoney(company.profit)} · ${company.margin}% margin`"
                        >
                            {{ formatMoneyCompact(company.profit) }}
                        </td>
                        <td class="text-end">
                            {{ company.vehicles_count }}
                            <span class="group-snapshot__muted">· {{ company.on_trip }} on trip</span>
                        </td>
                        <td class="group-snapshot__utilization">
                            <div class="d-flex align-items-center gap-2">
                                <div class="group-share-bar flex-grow-1">
                                    <div class="group-share-bar__fill" :style="{ width: `${Math.min(company.utilization_rate, 100)}%` }"></div>
                                </div>
                                <span class="group-snapshot__rate">{{ company.utilization_rate }}%</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="companies.length > 1">
                    <tr>
                        <th>All companies</th>
                        <th class="text-end">{{ totals.trips }}</th>
                        <th class="text-end">{{ formatMoneyCompact(totals.revenue) }}</th>
                        <th class="text-end">{{ formatMoneyCompact(totals.cost) }}</th>
                        <th class="text-end">{{ formatMoneyCompact(totals.profit) }}</th>
                        <th class="text-end">{{ totals.vehicles }}</th>
                        <th class="group-snapshot__utilization">{{ groupUtilization }}%</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatMoney, formatMoneyCompact } from '../../utils/formatMoney';
import './groupDashboard.css';

const props = defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
    groupUtilization: {
        type: Number,
        default: 0,
    },
});

const totals = computed(() =>
    props.companies.reduce(
        (sum, company) => ({
            trips: sum.trips + Number(company.trips_count ?? 0),
            revenue: sum.revenue + Number(company.revenue ?? 0),
            cost: sum.cost + Number(company.cost ?? 0),
            profit: sum.profit + Number(company.profit ?? 0),
            vehicles: sum.vehicles + Number(company.vehicles_count ?? 0),
        }),
        { trips: 0, revenue: 0, cost: 0, profit: 0, vehicles: 0 },
    ),
);

function subscriptionBadge(company) {
    const subscription = company.subscription;

    if (!subscription) {
        return { label: 'No plan', class: 'text-bg-secondary' };
    }

    if (subscription.status === 'expired') {
        return { label: 'Plan expired', class: 'text-bg-danger' };
    }

    if (subscription.days_remaining !== null && subscription.days_remaining <= 30) {
        return { label: `Renews in ${subscription.days_remaining}d`, class: 'text-bg-warning' };
    }

    return null;
}
</script>

<style scoped>
.group-snapshot {
    font-size: 0.8rem;
}

.group-snapshot__scroll {
    max-height: 22rem;
    overflow-y: auto;
}

.group-snapshot thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #ffffff;
}

.group-snapshot tfoot th {
    position: sticky;
    bottom: 0;
    background: #ffffff;
}

.group-snapshot thead th {
    font-size: 0.68rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom-width: 1px;
    white-space: nowrap;
}

.group-snapshot > :not(caption) > * > * {
    padding: 0.35rem 0.5rem;
}

.group-snapshot__name {
    font-weight: 700;
    color: #0f172a;
}

.group-snapshot__badge {
    margin-left: 0.4rem;
    font-size: 0.62rem;
    vertical-align: middle;
}

.group-snapshot__muted {
    font-size: 0.72rem;
    color: #94a3b8;
    white-space: nowrap;
}

.group-snapshot__utilization {
    min-width: 8.5rem;
}

.group-snapshot__rate {
    min-width: 2.8rem;
    font-weight: 600;
    text-align: right;
}

.group-snapshot tfoot th {
    font-size: 0.78rem;
    color: #0f172a;
    border-top: 2px solid #e2e8f0;
}
</style>
