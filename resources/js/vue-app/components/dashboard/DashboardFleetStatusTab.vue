<template>
    <div class="fleet-tab">
        <!-- Top Row: Fleet Status & Fleet Utilization -->
        <div class="row g-2 g-md-3">
            <!-- 1. Fleet Status Card -->
            <div class="col-12 col-xl-6">
                <div class="analytics-card flex-col-center h-100">
                    <div class="analytics-card__header-row">
                        <div>
                            <h6 class="analytics-card__title mb-0">
                                <i class="fas fa-truck-moving text-brand-primary me-1.5"></i>
                                Fleet Status
                            </h6>
                            <p class="analytics-card__hint mt-1 mb-0">Current deployment of fleet</p>
                        </div>
                        <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                            {{ totalTrucks }} Total Trucks
                        </span>
                    </div>

                    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-2 mt-2">
                        <div
                            v-for="item in fleetStatusCards"
                            :key="item.key"
                            class="col"
                        >
                            <div
                                class="fleet-status-item h-100"
                                :class="item.cardClass"
                            >
                                <div class="fleet-status-item__header">
                                    <span class="fleet-status-item__icon" :class="item.iconClass">
                                        <i :class="item.icon"></i>
                                    </span>
                                    <span class="fleet-status-item__value">{{ item.count }}</span>
                                </div>
                                <div class="fleet-status-item__label-row">
                                    <span class="fleet-status-item__label">{{ item.label }}</span>
                                    <span v-if="item.sublabel" class="fleet-status-item__sublabel">{{ item.sublabel }}</span>
                                </div>
                                <div class="fleet-status-item__bar-wrap">
                                    <div
                                        class="fleet-status-item__bar"
                                        :class="item.barClass"
                                        :style="{ width: `${item.pct}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="analytics-card__footer-info mt-auto pt-2 border-top">
                        <span>
                            <strong class="text-success">{{ activeTrucksCount }}</strong> active trucks ({{ activeTrucksPct.toFixed(0) }}%)
                        </span>
                        <span>
                            <strong class="text-muted">{{ unavailableCount }}</strong> unavailable
                        </span>
                    </div>
                </div>
            </div>

            <!-- 2. Fleet Utilization Report Card -->
            <div class="col-12 col-xl-6">
                <div class="analytics-card flex-col-center h-100">
                    <div class="analytics-card__header-row">
                        <div>
                            <h6 class="analytics-card__title mb-0">
                                <i class="fas fa-gauge-high text-brand-primary me-1.5"></i>
                                Fleet Utilization Report
                            </h6>
                            <p v-if="periodLabel" class="analytics-card__hint mt-1 mb-0">Period: {{ periodLabel }}</p>
                        </div>
                        <span
                            class="badge px-2 py-1"
                            :class="utilizationBadgeClass"
                        >
                            {{ utilizationRate.toFixed(1) }}% Utilization
                        </span>
                    </div>

                    <div class="row g-2 align-items-center mt-2">
                        <!-- Gauge column -->
                        <div class="col-12 col-sm-5 d-flex justify-content-center">
                            <div class="utilization-gauge">
                                <svg viewBox="0 0 100 60" class="utilization-gauge__svg" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="utilGradHigh" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.7" />
                                            <stop offset="100%" stop-color="#10b981" stop-opacity="1" />
                                        </linearGradient>
                                        <linearGradient id="utilGradMid" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#0284c7" stop-opacity="0.7" />
                                            <stop offset="100%" stop-color="#0284c7" stop-opacity="1" />
                                        </linearGradient>
                                        <linearGradient id="utilGradLow" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.7" />
                                            <stop offset="100%" stop-color="#f59e0b" stop-opacity="1" />
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
                                        :stroke="utilizationStroke"
                                        stroke-width="9"
                                        stroke-linecap="round"
                                        :stroke-dasharray="radialCircumference"
                                        :stroke-dashoffset="radialDashoffset"
                                        class="gauge-fill-arc"
                                    />
                                </svg>
                                <div class="utilization-gauge__content">
                                    <span class="utilization-gauge__value">{{ utilizationRate.toFixed(0) }}%</span>
                                    <span class="utilization-gauge__label">Utilization</span>
                                </div>
                            </div>
                        </div>

                        <!-- Underlying metrics numbers -->
                        <div class="col-12 col-sm-7">
                            <div class="utilization-numbers-table">
                                <div class="utilization-row">
                                    <span class="utilization-row__label">Total Fleet</span>
                                    <span class="utilization-row__value fw-bold">{{ totalFleet }}</span>
                                </div>
                                <div class="utilization-row">
                                    <span class="utilization-row__label">Available Days</span>
                                    <span class="utilization-row__value fw-bold text-dark">{{ availableDays }}</span>
                                </div>
                                <div class="utilization-row">
                                    <span class="utilization-row__label">
                                        <span class="status-dot status-dot--running"></span>
                                        Running Days
                                    </span>
                                    <span class="utilization-row__value fw-bold text-success">{{ runningDays }}</span>
                                </div>
                                <div class="utilization-row">
                                    <span class="utilization-row__label">
                                        <span class="status-dot status-dot--idle"></span>
                                        Idle Days
                                    </span>
                                    <span class="utilization-row__value fw-bold text-warning">{{ idleDays }}</span>
                                </div>
                                <div class="utilization-row">
                                    <span class="utilization-row__label">
                                        <span class="status-dot status-dot--unavailable"></span>
                                        Unavailable Days
                                    </span>
                                    <span class="utilization-row__value fw-bold text-muted">{{ unavailableDays }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Distribution Stack Bar -->
                    <div class="utilization-dist-bar mt-auto pt-2">
                        <div class="utilization-dist-bar__track">
                            <div
                                class="utilization-dist-bar__segment bg-success"
                                :style="{ width: `${runningPct}%` }"
                                :title="`Running: ${runningDays} days (${runningPct.toFixed(1)}%)`"
                            ></div>
                            <div
                                class="utilization-dist-bar__segment bg-warning"
                                :style="{ width: `${idlePct}%` }"
                                :title="`Idle: ${idleDays} days (${idlePct.toFixed(1)}%)`"
                            ></div>
                            <div
                                class="utilization-dist-bar__segment bg-secondary"
                                :style="{ width: `${unavailablePct}%` }"
                                :title="`Unavailable: ${unavailableDays} days (${unavailablePct.toFixed(1)}%)`"
                            ></div>
                        </div>
                        <div class="utilization-dist-bar__legend mt-1.5">
                            <span class="legend-item"><span class="status-dot status-dot--running"></span> Running {{ runningPct.toFixed(0) }}%</span>
                            <span class="legend-item"><span class="status-dot status-dot--idle"></span> Idle {{ idlePct.toFixed(0) }}%</span>
                            <span class="legend-item"><span class="status-dot status-dot--unavailable"></span> Inactive {{ unavailablePct.toFixed(0) }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Idle Trucks Card -->
        <div class="analytics-card mt-3">
            <div class="analytics-card__header-row flex-wrap gap-2">
                <div>
                    <h6 class="analytics-card__title mb-0">
                        <i class="fas fa-clock text-amber-500 me-1.5"></i>
                        Idle Trucks — {{ filterTitleLabel }}
                    </h6>
                    <p class="analytics-card__hint mt-1 mb-0">
                        Trucks currently waiting without an active trip
                    </p>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Quick Filter Buttons -->
                    <div class="idle-filter-pills">
                        <button
                            v-for="filter in filterOptions"
                            :key="filter.key"
                            class="idle-filter-btn"
                            :class="{ 'idle-filter-btn--active': activeFilter === filter.key }"
                            @click="activeFilter = filter.key"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <!-- Configure Thresholds Button -->
                    <button
                        class="btn btn-sm btn-outline-secondary py-1 px-2 py-md-1 px-md-3 rounded-pill config-toggle-btn"
                        @click="showThresholdConfig = !showThresholdConfig"
                        :class="{ 'btn-secondary text-white': showThresholdConfig }"
                        title="Configure idle day thresholds"
                    >
                        <i class="fas fa-sliders-h me-1"></i>
                        Thresholds
                    </button>
                </div>
            </div>

            <!-- Configurable Thresholds Inline Box -->
            <div v-if="showThresholdConfig" class="threshold-config-box mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="threshold-config-box__title fw-bold text-dark small">
                        <i class="fas fa-sliders-h text-brand-primary me-1"></i>
                        Configure Idle Thresholds (Days)
                    </span>
                    <button class="btn-close btn-sm shadow-none" @click="showThresholdConfig = false" aria-label="Close"></button>
                </div>
                <div class="row g-2">
                    <div class="col-sm-3 col-6">
                        <label class="form-label threshold-label text-muted mb-1 small">Normal Idle (max days)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-success-subtle text-success-emphasis border-0">1–</span>
                            <input
                                v-model.number="tempThresholds.normalMax"
                                type="number"
                                min="1"
                                max="30"
                                class="form-control form-control-sm threshold-input"
                            />
                        </div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <label class="form-label threshold-label text-muted mb-1 small">Attention (max days)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-warning-subtle text-warning-emphasis border-0">{{ tempThresholds.normalMax + 1 }}–</span>
                            <input
                                v-model.number="tempThresholds.attentionMax"
                                type="number"
                                :min="tempThresholds.normalMax + 1"
                                max="60"
                                class="form-control form-control-sm threshold-input"
                            />
                        </div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <label class="form-label threshold-label text-muted mb-1 small">Prolonged (max days)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-orange-subtle text-orange-emphasis border-0">{{ tempThresholds.attentionMax + 1 }}–</span>
                            <input
                                v-model.number="tempThresholds.prolongedMax"
                                type="number"
                                :min="tempThresholds.attentionMax + 1"
                                max="180"
                                class="form-control form-control-sm threshold-input"
                            />
                        </div>
                    </div>
                    <div class="col-sm-3 col-6">
                        <label class="form-label threshold-label text-muted mb-1 small">Default Filter (days)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0">≥</span>
                            <input
                                v-model.number="tempThresholds.defaultFilterDays"
                                type="number"
                                min="1"
                                max="180"
                                class="form-control form-control-sm threshold-input"
                            />
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end align-items-center gap-2 mt-2 pt-2 border-top">
                    <button class="btn btn-sm btn-link text-decoration-none text-muted py-0 small threshold-btn-reset" @click="resetThresholds">
                        Reset to Defaults
                    </button>
                    <button class="btn btn-sm btn-primary py-1 px-2 py-md-1 px-md-3 rounded-pill threshold-btn-apply" @click="saveThresholds">
                        Apply &amp; Save
                    </button>
                </div>
            </div>

            <!-- Search & Count Bar -->
            <div class="d-flex justify-content-between align-items-center mt-3 mb-2 flex-wrap gap-2">
                <div class="small text-muted">
                    Showing <strong>{{ filteredIdleTrucks.length }}</strong> of {{ allIdleTrucks.length }} idle trucks
                    <span v-if="activeFilter !== 'all'" class="badge bg-light text-secondary border ms-1">
                        {{ filterThresholdSummary }}
                    </span>
                </div>
                <div class="search-input-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="form-control form-control-sm search-input"
                        placeholder="Search truck or trip..."
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive idle-table-wrap">
                <table class="table table-sm table-hover align-middle mb-0 text-nowrap idle-table">
                    <thead>
                        <tr>
                            <th class="text-nowrap">Truck</th>
                            <th class="text-nowrap">Last Trip</th>
                            <th class="text-nowrap">Last Trip End</th>
                            <th class="text-nowrap">Idle Since</th>
                            <th class="text-nowrap">Idle Days</th>
                            <th class="text-nowrap">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="truck in filteredIdleTrucks" :key="truck.id">
                            <td>
                                <RouterLink
                                    :to="{ name: 'trucks.show', params: { vehicleId: truck.id } }"
                                    class="truck-reg-link"
                                >
                                    <i class="fas fa-truck text-muted me-1.5"></i>
                                    <strong>{{ truck.registration_number }}</strong>
                                </RouterLink>
                            </td>
                            <td>
                                <template v-if="truck.last_trip_number">
                                    <RouterLink
                                        :to="{ name: 'trips.show', params: { id: truck.last_trip_id } }"
                                        class="trip-num-link"
                                    >
                                        {{ truck.last_trip_number }}
                                    </RouterLink>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td class="text-secondary">
                                {{ truck.last_trip_end ?? '—' }}
                            </td>
                            <td class="text-secondary">
                                {{ truck.idle_since ?? '—' }}
                            </td>
                            <td>
                                <span class="fw-bold" :class="idleDaysColorClass(truck.idle_days)">
                                    {{ truck.idle_days }} {{ truck.idle_days === 1 ? 'day' : 'days' }}
                                </span>
                            </td>
                            <td>
                                <span
                                    class="badge fw-medium px-2 py-1"
                                    :class="severityBadgeClass(truck.idle_days)"
                                >
                                    {{ severityLabel(truck.idle_days) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="filteredIdleTrucks.length === 0">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <div class="py-2">
                                    <i class="fas fa-check-circle text-success fs-3 mb-2 d-block"></i>
                                    <span v-if="allIdleTrucks.length === 0">
                                        No trucks are currently idle. All active trucks are on trips!
                                    </span>
                                    <span v-else>
                                        No trucks matching current filter criteria.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
    fleetStatus: {
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
    periodLabel: {
        type: String,
        default: '',
    },
});

// 1. Configurable Thresholds State
const STORAGE_KEY = 'tb_fleet_idle_thresholds';
const DEFAULT_THRESHOLDS = {
    normalMax: 3,        // 1–3 days: normal idle
    attentionMax: 7,     // 4–7 days: attention
    prolongedMax: 15,    // 8–15 days: prolonged idle
    defaultFilterDays: 7,// Default filter is 7+ days as requested
};

const thresholds = ref({ ...DEFAULT_THRESHOLDS });
const tempThresholds = ref({ ...DEFAULT_THRESHOLDS });
const showThresholdConfig = ref(false);
const activeFilter = ref('default'); // 'default' (7+), 'all' (1+), 'attention', 'prolonged', 'critical'
const searchQuery = ref('');

onMounted(() => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            thresholds.value = {
                normalMax: Number(parsed.normalMax) || DEFAULT_THRESHOLDS.normalMax,
                attentionMax: Number(parsed.attentionMax) || DEFAULT_THRESHOLDS.attentionMax,
                prolongedMax: Number(parsed.prolongedMax) || DEFAULT_THRESHOLDS.prolongedMax,
                defaultFilterDays: Number(parsed.defaultFilterDays) || DEFAULT_THRESHOLDS.defaultFilterDays,
            };
            tempThresholds.value = { ...thresholds.value };
        }
    } catch {
        // Fallback to defaults
    }
});

function saveThresholds() {
    thresholds.value = { ...tempThresholds.value };
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(thresholds.value));
    } catch {
        // Ignore
    }
    showThresholdConfig.value = false;
}

function resetThresholds() {
    thresholds.value = { ...DEFAULT_THRESHOLDS };
    tempThresholds.value = { ...DEFAULT_THRESHOLDS };
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Ignore
    }
}

// 2. Fleet Status Overview
const overview = computed(() => props.fleetStatus?.overview ?? {});
const totalTrucks = computed(() => Number(overview.value.total_trucks ?? 0));
const onTripCount = computed(() => Number(overview.value.on_trip ?? 0));
const unavailableCount = computed(() => Number(overview.value.unavailable ?? 0));

// All idle trucks list
const allIdleTrucks = computed(() => props.fleetStatus?.idle_trucks ?? []);

// Dynamically compute Available and Idle counts based on configured normalMax
const availableCount = computed(() => {
    return allIdleTrucks.value.filter((t) => t.idle_days <= thresholds.value.normalMax).length;
});
const idleCount = computed(() => {
    return allIdleTrucks.value.filter((t) => t.idle_days > thresholds.value.normalMax).length;
});

const activeTrucksCount = computed(() => onTripCount.value + availableCount.value + idleCount.value);
const activeTrucksPct = computed(() => (totalTrucks.value > 0 ? (activeTrucksCount.value / totalTrucks.value) * 100 : 0));

function statusPct(count) {
    if (totalTrucks.value <= 0) return 0;
    return Math.max(0, Math.min((count / totalTrucks.value) * 100, 100));
}

const fleetStatusCards = computed(() => [
    {
        key: 'total',
        label: 'Total Trucks',
        count: totalTrucks.value,
        pct: 100,
        barClass: 'bg-dark',
        iconClass: 'text-dark bg-light',
        icon: 'fas fa-truck',
        cardClass: 'fleet-status-item--total',
    },
    {
        key: 'on_trip',
        label: 'On Trip',
        count: onTripCount.value,
        pct: statusPct(onTripCount.value),
        barClass: 'bg-success',
        iconClass: 'text-success bg-success-subtle',
        icon: 'fas fa-route',
        cardClass: 'fleet-status-item--running',
    },
    {
        key: 'available',
        label: 'Available',
        count: availableCount.value,
        pct: statusPct(availableCount.value),
        barClass: 'bg-primary',
        iconClass: 'text-primary bg-primary-subtle',
        icon: 'fas fa-check-circle',
        cardClass: 'fleet-status-item--available',
    },
    {
        key: 'idle',
        label: 'Idle',
        count: idleCount.value,
        pct: statusPct(idleCount.value),
        barClass: 'bg-warning',
        iconClass: 'text-warning bg-warning-subtle',
        icon: 'fas fa-pause-circle',
        cardClass: 'fleet-status-item--idle',
    },
    {
        key: 'unavailable',
        label: 'Unavailable',
        sublabel: '(which is inactive)',
        count: unavailableCount.value,
        pct: statusPct(unavailableCount.value),
        barClass: 'bg-secondary',
        iconClass: 'text-secondary bg-secondary-subtle',
        icon: 'fas fa-ban',
        cardClass: 'fleet-status-item--unavailable',
    },
]);

// 3. Fleet Utilization Report
const utilization = computed(() => props.fleetStatus?.utilization ?? {});
const totalFleet = computed(() => Number(utilization.value.total_fleet ?? totalTrucks.value));
const availableDays = computed(() => Number(utilization.value.available_days ?? 0));
const runningDays = computed(() => Number(utilization.value.running_days ?? 0));
const idleDays = computed(() => Number(utilization.value.idle_days ?? 0));
const unavailableDays = computed(() => Number(utilization.value.unavailable_days ?? 0));
const utilizationRate = computed(() => Number(utilization.value.utilization_rate ?? 0));

const runningPct = computed(() => (availableDays.value > 0 ? (runningDays.value / availableDays.value) * 100 : 0));
const idlePct = computed(() => (availableDays.value > 0 ? (idleDays.value / availableDays.value) * 100 : 0));
const unavailablePct = computed(() => (availableDays.value > 0 ? (unavailableDays.value / availableDays.value) * 100 : 0));

// Gauge Configuration
const radialCircumference = Math.PI * 42;
const radialDashoffset = computed(() => {
    const clamped = Math.min(Math.max(utilizationRate.value, 0), 100);
    return radialCircumference - (clamped / 100) * radialCircumference;
});

const utilizationStroke = computed(() => {
    if (utilizationRate.value >= 70) return 'url(#utilGradHigh)';
    if (utilizationRate.value >= 40) return 'url(#utilGradMid)';
    return 'url(#utilGradLow)';
});

const utilizationBadgeClass = computed(() => {
    if (utilizationRate.value >= 70) return 'bg-success-subtle text-success-emphasis border border-success-subtle';
    if (utilizationRate.value >= 40) return 'bg-primary-subtle text-primary-emphasis border border-primary-subtle';
    return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
});

// 4. Idle Trucks Filter and List
const filterOptions = computed(() => [
    { key: 'default', label: `Not Used for ${thresholds.value.defaultFilterDays}+ Days` },
    { key: 'attention', label: `Attention (${thresholds.value.normalMax + 1}–${thresholds.value.attentionMax} d)` },
    { key: 'prolonged', label: `Prolonged (${thresholds.value.attentionMax + 1}–${thresholds.value.prolongedMax} d)` },
    { key: 'critical', label: `Critical (${thresholds.value.prolongedMax + 1}+ d)` },
    { key: 'all', label: 'All Idle (1+ d)' },
]);

const filterTitleLabel = computed(() => {
    if (activeFilter.value === 'default') return `Not Used for ${thresholds.value.defaultFilterDays}+ Days`;
    if (activeFilter.value === 'attention') return `Needs Attention (${thresholds.value.normalMax + 1}–${thresholds.value.attentionMax} Days)`;
    if (activeFilter.value === 'prolonged') return `Prolonged Idle (${thresholds.value.attentionMax + 1}–${thresholds.value.prolongedMax} Days)`;
    if (activeFilter.value === 'critical') return `Critical / Long Idle (${thresholds.value.prolongedMax + 1}+ Days)`;
    return 'All Idle Trucks';
});

const filterThresholdSummary = computed(() => {
    if (activeFilter.value === 'default') return `≥ ${thresholds.value.defaultFilterDays} days`;
    if (activeFilter.value === 'attention') return `${thresholds.value.normalMax + 1}–${thresholds.value.attentionMax} days`;
    if (activeFilter.value === 'prolonged') return `${thresholds.value.attentionMax + 1}–${thresholds.value.prolongedMax} days`;
    if (activeFilter.value === 'critical') return `≥ ${thresholds.value.prolongedMax + 1} days`;
    return 'All days';
});

const filteredIdleTrucks = computed(() => {
    let list = allIdleTrucks.value;

    // Filter by threshold tier
    if (activeFilter.value === 'default') {
        list = list.filter((t) => t.idle_days >= thresholds.value.defaultFilterDays);
    } else if (activeFilter.value === 'attention') {
        list = list.filter(
            (t) => t.idle_days > thresholds.value.normalMax && t.idle_days <= thresholds.value.attentionMax,
        );
    } else if (activeFilter.value === 'prolonged') {
        list = list.filter(
            (t) => t.idle_days > thresholds.value.attentionMax && t.idle_days <= thresholds.value.prolongedMax,
        );
    } else if (activeFilter.value === 'critical') {
        list = list.filter((t) => t.idle_days > thresholds.value.prolongedMax);
    }

    // Filter by search query
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter((t) => {
            const reg = (t.registration_number ?? '').toLowerCase();
            const trip = (t.last_trip_number ?? '').toLowerCase();
            return reg.includes(q) || trip.includes(q);
        });
    }

    return list;
});

function severityLabel(days) {
    if (days <= thresholds.value.normalMax) return 'Normal Idle';
    if (days <= thresholds.value.attentionMax) return 'Attention';
    if (days <= thresholds.value.prolongedMax) return 'Prolonged Idle';
    return 'Critical Idle';
}

function severityBadgeClass(days) {
    if (days <= thresholds.value.normalMax) {
        return 'bg-success-subtle text-success-emphasis border border-success-subtle';
    }
    if (days <= thresholds.value.attentionMax) {
        return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    }
    if (days <= thresholds.value.prolongedMax) {
        return 'bg-orange-subtle text-orange-emphasis border border-orange-subtle';
    }
    return 'bg-danger-subtle text-danger-emphasis border border-danger-subtle';
}

function idleDaysColorClass(days) {
    if (days <= thresholds.value.normalMax) return 'text-success';
    if (days <= thresholds.value.attentionMax) return 'text-warning';
    if (days <= thresholds.value.prolongedMax) return 'text-orange';
    return 'text-danger';
}
</script>

<style scoped>
.fleet-tab {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fleet-tab__top-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    align-items: stretch;
}

@media (max-width: 991.98px) {
    .fleet-tab__top-grid {
        grid-template-columns: 1fr;
    }
}

/* Card Header */
.analytics-card__header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

/* Fleet Status Grid */
.fleet-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.75rem;
}

.fleet-status-item {
    background: #ffffff;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 0.75rem;
    padding: 0.75rem 0.85rem;
    display: flex;
    flex-direction: column;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.fleet-status-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.fleet-status-item__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.fleet-status-item__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.fleet-status-item__value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}

.fleet-status-item__label-row {
    display: flex;
    flex-direction: column;
    min-height: 2.2rem;
    margin-bottom: 0.4rem;
}

.fleet-status-item__label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}

.fleet-status-item__sublabel {
    font-size: 0.68rem;
    color: #94a3b8;
    line-height: 1.2;
}

.fleet-status-item__bar-wrap {
    height: 4px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
    margin-top: auto;
}

.fleet-status-item__bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}

/* Utilization Card */
.utilization-body {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 1rem;
    align-items: center;
}

@media (max-width: 575.98px) {
    .utilization-body {
        grid-template-columns: 1fr;
    }
}

.utilization-gauge-col {
    display: flex;
    justify-content: center;
    align-items: center;
}

.utilization-gauge {
    position: relative;
    width: 130px;
    height: 80px;
}

.utilization-gauge__svg {
    width: 100%;
    height: 100%;
    overflow: visible;
}

.utilization-gauge__content {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding-top: 1rem;
}

.utilization-gauge__value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
}

.utilization-gauge__label {
    font-size: 0.65rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.15rem;
}

.utilization-numbers-table {
    background: #ffffff;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 0.75rem;
    overflow: hidden;
}

.utilization-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.42rem 0.75rem;
    font-size: 0.78rem;
    border-bottom: 1px solid #f1f5f9;
}

.utilization-row:last-child {
    border-bottom: none;
}

.utilization-row__label {
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}

.status-dot--running {
    background: #10b981;
}

.status-dot--idle {
    background: #f59e0b;
}

.status-dot--unavailable {
    background: #94a3b8;
}

/* Distribution Bar */
.utilization-dist-bar__track {
    display: flex;
    height: 8px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
}

.utilization-dist-bar__segment {
    height: 100%;
    transition: width 0.4s ease;
}

.utilization-dist-bar__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    font-size: 0.72rem;
    color: #64748b;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Idle Filter Pills */
.idle-filter-pills {
    display: flex;
    flex-wrap: wrap;
    background: #f1f5f9;
    padding: 0.2rem;
    border-radius: 999px;
    gap: 0.2rem;
}

.idle-filter-btn {
    border: none;
    outline: none;
    background: transparent;
    padding: 0.3rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.idle-filter-btn:hover {
    color: #0f172a;
}

.idle-filter-btn--active {
    background: #ffffff;
    color: var(--brand-primary-dark, #23965c);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}

.config-toggle-btn {
    font-size: 0.72rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
}

/* Threshold Config Box */
.threshold-config-box {
    background: #f8fafc;
    border: 1px dashed var(--brand-border, #cbd5e1);
    border-radius: 0.75rem;
    padding: 0.85rem 1rem;
    animation: fadeIn 0.2s ease;
}

/* Search Input */
.search-input-wrap {
    position: relative;
    width: 220px;
}

.search-icon {
    position: absolute;
    left: 0.7rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.75rem;
    color: #94a3b8;
}

.search-input {
    padding-left: 2rem;
    border-radius: 999px;
    font-size: 0.76rem;
}

/* Idle Table */
.idle-table-wrap {
    background: #ffffff;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 0.75rem;
}

.idle-table th {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    background: #f8fafc;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--brand-border, #e2e8f0);
    white-space: nowrap !important;
}

.idle-table td {
    padding: 0.7rem 0.85rem;
    font-size: 0.8rem;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap !important;
}

.idle-table tbody tr:last-child td {
    border-bottom: none;
}

.truck-reg-link {
    text-decoration: none;
    color: #0f172a;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.82rem;
}

.truck-reg-link:hover {
    color: var(--brand-primary, #2fb36f);
    text-decoration: underline;
}

.trip-num-link {
    text-decoration: none;
    color: var(--brand-primary, #2fb36f);
    font-weight: 600;
    font-size: 0.78rem;
}

.trip-num-link:hover {
    text-decoration: underline;
}

.text-orange {
    color: #ea580c !important;
}

.bg-orange-subtle {
    background-color: #ffedd5 !important;
}

.text-orange-emphasis {
    color: #c2410c !important;
}

.border-orange-subtle {
    border-color: #fed7aa !important;
}

/* Mobile Screen Typography and Layout scaling */
@media (max-width: 767.98px) {
    .fleet-tab {
        gap: 0.75rem;
        max-width: 100%;
        overflow-x: clip;
    }

    .analytics-card__header-row {
        gap: 0.45rem;
    }

    .analytics-card__title {
        font-size: 0.85rem;
    }

    .analytics-card__hint {
        font-size: 0.7rem;
    }

    .fleet-status-item {
        padding: 0.45rem 0.55rem;
        border-radius: 0.6rem;
    }

    .fleet-status-item__icon {
        width: 1.55rem;
        height: 1.55rem;
        font-size: 0.7rem;
        border-radius: 0.4rem;
    }

    .fleet-status-item__value {
        font-size: 1.05rem;
    }

    .fleet-status-item__label-row {
        min-height: 1.8rem;
        margin-bottom: 0.25rem;
    }

    .fleet-status-item__label {
        font-size: 0.68rem;
    }

    .fleet-status-item__sublabel {
        font-size: 0.58rem;
    }

    .analytics-card__footer-info {
        font-size: 0.72rem;
    }

    .utilization-gauge {
        width: 110px;
        height: 65px;
    }

    .utilization-gauge__value {
        font-size: 1.15rem;
    }

    .utilization-gauge__label {
        font-size: 0.56rem;
    }

    .utilization-numbers-table {
        border-radius: 0.6rem;
    }

    .utilization-row {
        padding: 0.32rem 0.55rem;
        font-size: 0.72rem;
    }

    .status-dot {
        width: 6px;
        height: 6px;
    }

    .utilization-dist-bar__legend {
        gap: 0.45rem;
        font-size: 0.64rem;
    }

    .idle-filter-pills {
        padding: 0.15rem;
        gap: 0.15rem;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .idle-filter-btn {
        padding: 0.18rem 0.4rem;
        font-size: 0.62rem;
    }

    .config-toggle-btn {
        padding: 0.16rem 0.42rem;
        font-size: 0.60rem;
    }

    .config-toggle-btn i {
        font-size: 0.58rem;
    }

    /* Threshold Config Box mobile tuning */
    .threshold-config-box {
        padding: 0.45rem 0.55rem;
        border-radius: 0.5rem;
    }

    .threshold-config-box__title {
        font-size: 0.68rem;
    }

    .threshold-config-box__title i {
        font-size: 0.65rem;
    }

    .threshold-config-box .btn-close {
        transform: scale(0.62);
        padding: 0.12rem;
    }

    .threshold-label {
        font-size: 0.58rem;
        margin-bottom: 0.12rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }

    .threshold-config-box .input-group {
        min-height: 24px;
    }

    .threshold-config-box .input-group-text {
        font-size: 0.60rem;
        padding: 0.1rem 0.3rem;
        line-height: 1.2;
    }

    .threshold-input {
        font-size: 0.64rem;
        padding: 0.1rem 0.3rem;
        min-height: 24px;
        height: 24px;
        line-height: 1.2;
    }

    .threshold-btn-reset {
        font-size: 0.58rem;
        padding: 0.12rem 0.3rem !important;
        line-height: 1.2;
    }

    .threshold-btn-apply {
        font-size: 0.60rem;
        padding: 0.16rem 0.5rem !important;
        border-radius: 999px;
        line-height: 1.2;
    }

    .search-input-wrap {
        width: 100%;
    }

    .search-input {
        font-size: 0.72rem;
        padding-left: 1.8rem;
    }

    .idle-table th {
        font-size: 0.66rem;
        padding: 0.42rem 0.6rem;
        white-space: nowrap !important;
    }

    .idle-table td {
        font-size: 0.74rem;
        padding: 0.42rem 0.6rem;
        white-space: nowrap !important;
    }

    .truck-reg-link {
        font-size: 0.76rem;
        white-space: nowrap !important;
    }

    .trip-num-link {
        font-size: 0.74rem;
        white-space: nowrap !important;
    }
}

@media (min-width: 768px) and (max-width: 991.98px) {
    .config-toggle-btn {
        padding: 0.22rem 0.5rem;
        font-size: 0.68rem;
    }

    .threshold-config-box {
        padding: 0.65rem 0.8rem;
    }

    .threshold-config-box__title {
        font-size: 0.74rem;
    }

    .threshold-label {
        font-size: 0.65rem;
    }

    .threshold-config-box .input-group-text,
    .threshold-input {
        font-size: 0.68rem;
        min-height: 28px;
        height: 28px;
    }

    .threshold-btn-reset {
        font-size: 0.64rem;
    }

    .threshold-btn-apply {
        font-size: 0.66rem;
        padding: 0.22rem 0.7rem !important;
    }
}
</style>
