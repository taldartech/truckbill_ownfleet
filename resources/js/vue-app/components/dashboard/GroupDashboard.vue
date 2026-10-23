<template>
    <div class="group-dashboard">
        <header class="group-dashboard__hero">
            <div class="group-dashboard__hero-content">
                <h2 class="group-dashboard__title">{{ greetingMessage }}</h2>
                <p class="group-dashboard__subtitle mb-0">
                    <i class="fas fa-layer-group me-1"></i>
                    {{ scopeDescription }}
                    <span v-if="comparisonRangeLabel" class="group-dashboard__compare">
                        · compared with {{ comparisonRangeLabel }}
                    </span>
                </p>
            </div>
            <div class="group-dashboard__filters">
                <div class="group-dashboard__filter-row">
                    <label class="group-dashboard__filter">
                        <span class="group-dashboard__filter-label">Company</span>
                        <select v-model="companyId" class="form-select group-dashboard__select" @change="loadDashboard">
                            <option value="">All Companies</option>
                            <option v-for="company in companyOptions" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                    </label>
                    <div class="group-dashboard__filter group-dashboard__filter--period">
                        <DashboardDateRangePicker
                            v-model:from-date="periodFromDate"
                            v-model:to-date="periodToDate"
                            label="Period"
                            :preset-keys="DASHBOARD_DATE_PRESET_KEYS"
                            :max-months="MAX_LIST_DATE_RANGE_MONTHS"
                            @change="loadDashboard"
                        />
                    </div>
                </div>
            </div>
        </header>

        <div v-if="loading && !stats" class="py-5 text-center text-muted">Loading group overview…</div>

        <div v-else-if="loadError" class="alert alert-danger mb-0">{{ loadError }}</div>

        <div v-else-if="stats" class="group-dashboard__body" :class="{ 'group-dashboard__body--refreshing': loading }">
            <GroupExecutiveKpis :executive="stats.executive" :comparison-label="comparisonLabel" />

            <GroupCompanySnapshot :companies="stats.companies" :group-utilization="stats.fleet.utilization.rate" />

            <div class="group-dashboard__row group-dashboard__row--halves">
                <GroupFleetOverview :overview="stats.fleet.overview" @show-idle="openIdleVehicles(stats.fleet.idle.warning_days)" />
                <GroupBusinessActivity :activity="stats.activity" />
            </div>

            <div class="group-dashboard__row">
                <GroupProfitabilityTrend
                    :months="stats.trend"
                    :trend-months="trendMonths"
                    @update:trend-months="onTrendMonthsChange"
                />
                <GroupAttentionPanel :items="stats.attention" />
            </div>

            <div class="group-dashboard__row group-dashboard__row--thirds">
                <GroupCashPosition :cash="stats.cash" />
                <GroupFleetUtilization :fleet="stats.fleet" @show-idle="openIdleVehicles" />
                <GroupExpenseBreakdown :expenses="stats.expenses" />
            </div>
        </div>

        <GroupIdleVehiclesModal
            v-if="stats"
            :show="idleModalThreshold !== null"
            :vehicles="stats.fleet.idle.vehicles"
            :warning-days="stats.fleet.idle.warning_days"
            :critical-days="stats.fleet.idle.critical_days"
            :initial-threshold="idleModalThreshold ?? stats.fleet.idle.warning_days"
            :total-count="stats.fleet.idle.over_warning_days"
            @close="idleModalThreshold = null"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchGroupDashboard } from '../../api/dashboard';
import { useOperationalDataInvalidation } from '../../composables/useOperationalDataInvalidation';
import {
    DASHBOARD_DATE_PRESET_KEYS,
    defaultDashboardTripRange,
    formatDateRangeLabel,
    MAX_LIST_DATE_RANGE_MONTHS,
} from '../../utils/dateRangePresets';
import DashboardDateRangePicker from './DashboardDateRangePicker.vue';
import GroupAttentionPanel from './GroupAttentionPanel.vue';
import GroupBusinessActivity from './GroupBusinessActivity.vue';
import GroupCashPosition from './GroupCashPosition.vue';
import GroupCompanySnapshot from './GroupCompanySnapshot.vue';
import GroupExecutiveKpis from './GroupExecutiveKpis.vue';
import GroupExpenseBreakdown from './GroupExpenseBreakdown.vue';
import GroupFleetOverview from './GroupFleetOverview.vue';
import GroupFleetUtilization from './GroupFleetUtilization.vue';
import GroupIdleVehiclesModal from './GroupIdleVehiclesModal.vue';
import GroupProfitabilityTrend from './GroupProfitabilityTrend.vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
});

const defaultRange = defaultDashboardTripRange();
const periodFromDate = ref(defaultRange.from_date);
const periodToDate = ref(defaultRange.to_date);
const companyId = ref('');
const trendMonths = ref(12);

const loading = ref(true);
const loadError = ref('');
const stats = ref(null);
const idleModalThreshold = ref(null);

const companyOptions = computed(() => stats.value?.filters?.companies ?? []);

const greetingMessage = computed(() => {
    const hour = new Date().getHours();
    const name = props.user?.name ? `, ${props.user.name}` : '';

    if (hour < 12) return `Good Morning${name}! ☀️`;
    if (hour < 17) return `Good Afternoon${name}! 🌤️`;

    return `Good Evening${name}! 🌙`;
});

const scopeDescription = computed(() => {
    const selected = companyOptions.value.find((company) => company.id === companyId.value);

    if (selected) {
        return `Showing ${selected.name} only`;
    }

    const count = companyOptions.value.length;

    return `Combined view of ${count} ${count === 1 ? 'company' : 'companies'}`;
});

const comparisonRangeLabel = computed(() => {
    const period = stats.value?.period;

    return period ? formatDateRangeLabel(period.previous_from_date, period.previous_to_date) : '';
});

const comparisonLabel = computed(() => (comparisonRangeLabel.value ? `vs ${comparisonRangeLabel.value}` : 'vs previous period'));

function openIdleVehicles(threshold) {
    idleModalThreshold.value = threshold;
}

function onTrendMonthsChange(months) {
    if (trendMonths.value === months) {
        return;
    }

    trendMonths.value = months;
    loadDashboard();
}

async function loadDashboard() {
    loading.value = true;
    loadError.value = '';

    try {
        stats.value = await fetchGroupDashboard({
            from_date: periodFromDate.value,
            to_date: periodToDate.value,
            company_id: companyId.value || undefined,
            trend_months: trendMonths.value,
        });
    } catch (error) {
        const errors = error?.response?.data?.errors ?? {};
        const validationMessage = errors.from_date?.[0] ?? errors.to_date?.[0] ?? errors.company_id?.[0];

        loadError.value = validationMessage ?? 'Could not load the group overview.';
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);

useOperationalDataInvalidation('dashboard', loadDashboard);
</script>

<style scoped>
.group-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.group-dashboard__hero {
    position: relative;
    z-index: 10;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1.5rem;
    padding: 0.9rem 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(47, 179, 111, 0.1) 0%, rgba(255, 255, 255, 0.9) 60%);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
}

.group-dashboard__hero-content {
    flex: 1 1 18rem;
    min-width: 0;
}

.group-dashboard__title {
    margin: 0 0 0.2rem;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}

.group-dashboard__subtitle {
    color: var(--brand-text-muted, #64748b);
    font-size: 0.82rem;
}

.group-dashboard__subtitle i {
    color: var(--brand-primary, #2fb36f);
}

.group-dashboard__compare {
    color: #94a3b8;
}

.group-dashboard__filters,
.group-dashboard__filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.6rem;
}

.group-dashboard__filter {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    width: 12rem;
    margin: 0;
}

.group-dashboard__filter-label,
.group-dashboard__filter--period :deep(.dashboard-date-range__label) {
    display: block;
    margin: 0;
    font-size: 0.64rem;
    font-weight: 700;
    line-height: 1.2;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.group-dashboard__filter--period :deep(.dashboard-date-range) {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    width: 100%;
    margin-left: 0;
}

.group-dashboard__select,
.group-dashboard__filter--period :deep(.dashboard-date-range__trigger) {
    height: 2.25rem;
    padding-top: 0;
    padding-bottom: 0;
    border: 1px solid #dbe3ef;
    border-radius: 0.5rem;
    background-color: #ffffff;
    font-size: 0.85rem;
    font-weight: 500;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.group-dashboard__body {
    container: group-dashboard / inline-size;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: opacity 0.2s ease;
}

.group-dashboard__body--refreshing {
    opacity: 0.6;
    pointer-events: none;
}

.group-dashboard__row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 1rem;
    align-items: stretch;
}

.group-dashboard__row--halves {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.group-dashboard__row--thirds {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

@container group-dashboard (max-width: 960px) {
    .group-dashboard__row {
        grid-template-columns: 1fr;
    }

    .group-dashboard__row--halves,
    .group-dashboard__row--thirds {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .group-dashboard__row--thirds > :first-child {
        grid-column: 1 / -1;
    }
}

@container group-dashboard (max-width: 680px) {
    .group-dashboard__row--halves,
    .group-dashboard__row--thirds {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .group-dashboard__hero {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
    }

    .group-dashboard__title {
        font-size: 1.1rem;
    }

    .group-dashboard__subtitle {
        font-size: 0.72rem;
    }

    .group-dashboard__filters {
        width: 100% !important;
    }

    .group-dashboard__filter-row {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: flex-end !important;
        gap: 0.35rem !important;
        width: 100% !important;
    }

    .group-dashboard__filter {
        flex: 1 1 0 !important;
        width: calc(50% - 0.175rem) !important;
        max-width: calc(50% - 0.175rem) !important;
        min-width: 0 !important;
        gap: 0.15rem !important;
    }

    .group-dashboard__filter-label,
    .group-dashboard__filter--period :deep(.dashboard-date-range__label) {
        font-size: 0.62rem !important;
        margin-bottom: 0.1rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .group-dashboard__select,
    .group-dashboard__filter--period :deep(.dashboard-date-range__trigger) {
        height: 32px !important;
        min-height: 32px !important;
        font-size: 0.72rem !important;
        border-radius: 6px !important;
    }

    .group-dashboard__select {
        padding: 0.2rem 1.35rem 0.2rem 0.45rem !important;
        background-position: right 0.45rem center !important;
        background-size: 10px 10px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
        width: 100% !important;
    }

    .group-dashboard__filter--period :deep(.dashboard-date-range) {
        width: 100% !important;
        min-width: 0 !important;
        gap: 0.15rem !important;
    }

    .group-dashboard__filter--period :deep(.dashboard-date-range__trigger) {
        padding: 0 0.45rem !important;
        width: 100% !important;
        min-width: 0 !important;
        gap: 0.25rem !important;
        display: flex !important;
        align-items: center !important;
    }

    .group-dashboard__filter--period :deep(.dashboard-date-range__icon) {
        font-size: 0.72rem !important;
        flex-shrink: 0 !important;
    }

    .group-dashboard__filter--period :deep(.dashboard-date-range__value) {
        font-size: 0.72rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
    }

    .group-dashboard__filter--period :deep(.dashboard-date-range__chevron) {
        font-size: 0.62rem !important;
        flex-shrink: 0 !important;
        margin-left: auto !important;
    }
}
</style>
