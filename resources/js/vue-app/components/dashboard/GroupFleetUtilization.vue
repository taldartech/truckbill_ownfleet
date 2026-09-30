<template>
    <section class="group-panel">
        <header class="group-panel__header">
            <div>
                <h5 class="group-panel__title">
                    <i class="fas fa-gauge-high me-2"></i>
                    Fleet Utilization
                </h5>
                <p class="group-panel__subtitle">Truck-days on trip vs active truck-days in the period</p>
            </div>
        </header>

        <div class="group-utilization__top">
            <div class="group-utilization__gauge" :style="{ '--gauge-value': `${gaugeValue}%` }">
                <div class="group-utilization__gauge-inner">
                    <strong>{{ utilization.rate }}%</strong>
                    <span>utilized</span>
                </div>
            </div>
            <div class="group-utilization__facts">
                <div>
                    <span class="group-stat__label">Running truck-days</span>
                    <span class="group-utilization__fact">{{ utilization.running_days }}</span>
                </div>
                <div>
                    <span class="group-stat__label">Available truck-days</span>
                    <span class="group-utilization__fact">{{ utilization.available_days }}</span>
                </div>
                <div>
                    <span class="group-stat__label">On trip now</span>
                    <span class="group-utilization__fact">{{ overview.on_trip }} / {{ activeTrucks }}</span>
                </div>
            </div>
        </div>

        <p class="group-panel__section-label">Trucks not used recently</p>
        <div class="group-stat-grid group-utilization__idle">
            <button type="button" class="group-stat group-utilization__idle-card--warning" @click="$emit('show-idle', idle.warning_days)">
                <span class="group-stat__label"><i class="fas fa-hourglass-half"></i> Idle {{ idle.warning_days }}+ days</span>
                <span class="group-stat__value">{{ idle.over_warning_days }}</span>
                <span class="group-stat__hint">View trucks <i class="fas fa-arrow-right ms-1"></i></span>
            </button>
            <button type="button" class="group-stat group-utilization__idle-card--critical" @click="$emit('show-idle', idle.critical_days)">
                <span class="group-stat__label"><i class="fas fa-hourglass-end"></i> Idle {{ idle.critical_days }}+ days</span>
                <span class="group-stat__value">{{ idle.over_critical_days }}</span>
                <span class="group-stat__hint">View trucks <i class="fas fa-arrow-right ms-1"></i></span>
            </button>
        </div>

        <p class="group-panel__section-label mt-3">Trucks on trip by company</p>
        <div v-if="!onTripByCompany.length" class="group-utilization__none">No trucks on a trip right now.</div>
        <ul v-else class="group-list group-utilization__on-trip">
            <li v-for="row in onTripByCompany" :key="row.company_id" class="group-list__item">
                <span class="group-list__primary group-utilization__company">{{ row.company_name }}</span>
                <div class="group-share-bar flex-grow-1">
                    <div class="group-share-bar__fill" :style="{ width: `${shareOfOnTrip(row.count)}%` }"></div>
                </div>
                <span class="group-list__value">{{ row.count }}</span>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import './groupDashboard.css';

const props = defineProps({
    fleet: {
        type: Object,
        required: true,
    },
});

defineEmits(['show-idle']);

const utilization = computed(() => props.fleet.utilization ?? {});
const overview = computed(() => props.fleet.overview ?? {});
const idle = computed(() => props.fleet.idle ?? {});
const onTripByCompany = computed(() => props.fleet.on_trip_by_company ?? []);

const gaugeValue = computed(() => Math.min(Math.max(Number(utilization.value.rate ?? 0), 0), 100));

const activeTrucks = computed(
    () => Number(overview.value.total_trucks ?? 0) - Number(overview.value.unavailable ?? 0),
);

function shareOfOnTrip(count) {
    const total = Number(overview.value.on_trip ?? 0);

    return total > 0 ? (Number(count) / total) * 100 : 0;
}
</script>

<style scoped>
.group-utilization__top {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.8rem;
}

.group-utilization__gauge {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 6rem;
    height: 6rem;
    border-radius: 50%;
    background: conic-gradient(#2fb36f var(--gauge-value), #e2e8f0 0);
}

.group-utilization__gauge-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 4.6rem;
    height: 4.6rem;
    border-radius: 50%;
    background: #ffffff;
}

.group-utilization__gauge-inner strong {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
}

.group-utilization__gauge-inner span {
    font-size: 0.68rem;
    color: #94a3b8;
}

.group-utilization__facts {
    display: grid;
    flex: 1;
    grid-template-columns: repeat(auto-fit, minmax(7rem, 1fr));
    gap: 0.5rem 1rem;
}

.group-utilization__facts > div {
    display: flex;
    flex-direction: column;
}

.group-utilization__fact {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.group-utilization__idle {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.group-utilization__idle-card--warning {
    background: #fffbeb;
}

.group-utilization__idle-card--warning .group-stat__label i {
    color: #d97706;
}

.group-utilization__idle-card--critical {
    background: #fef2f2;
}

.group-utilization__idle-card--critical .group-stat__label i {
    color: #dc2626;
}

.group-utilization__company {
    flex: 0 0 40%;
}

.group-utilization__on-trip {
    max-height: 10rem;
    overflow-y: auto;
}

.group-utilization__none {
    font-size: 0.8rem;
    color: #94a3b8;
}
</style>
