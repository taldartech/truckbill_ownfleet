<template>
    <div class="dashboard-panel">
        <div class="dashboard-panel__head">
            <h6 class="dashboard-panel__title">
                <i class="fas fa-truck-fast me-2 text-primary"></i>
                Running Trips
            </h6>
            <RouterLink :to="runningTripsRoute" class="dashboard-panel__link">
                View all
            </RouterLink>
        </div>

        <div v-if="trips.length === 0" class="dashboard-panel__empty text-muted">
            No trips running right now.
        </div>

        <div v-else class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 dashboard-panel__table">
                <thead class="table-light">
                    <tr>
                        <th>Trip</th>
                        <th>Truck</th>
                        <th>Driver</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="trip in trips"
                        :key="trip.id"
                        class="dashboard-panel__row"
                        tabindex="0"
                        role="button"
                        @click="openTrip(trip.id)"
                        @keydown.enter.prevent="openTrip(trip.id)"
                    >
                        <td class="fw-medium">{{ trip.trip_number ?? '—' }}</td>
                        <td>{{ trip.vehicle_registration ?? '—' }}</td>
                        <td>{{ trip.driver_name ?? '—' }}</td>
                        <td>{{ formatDateTime(trip.start_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { formatDateTime } from '../../utils/datetime';

const props = defineProps({
    trips: {
        type: Array,
        default: () => [],
    },
    tripsIndexRoute: {
        type: Object,
        required: true,
    },
});

const router = useRouter();

const runningTripsRoute = computed(() => ({
    ...props.tripsIndexRoute,
    query: {
        ...props.tripsIndexRoute.query,
        status: 'IN_PROGRESS',
    },
}));

function openTrip(tripId) {
    router.push({ name: 'trips.show', params: { id: tripId } });
}

</script>

<style scoped>
.dashboard-panel {
    padding: 0.9rem 1rem;
    border: 1px solid var(--brand-border, #e2e8f0);
    border-radius: 0.85rem;
    background: #fff;
}

.dashboard-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.dashboard-panel__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.dashboard-panel__link {
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
}

.dashboard-panel__empty {
    padding: 0.75rem 0;
    font-size: 0.88rem;
}

.dashboard-panel__row {
    cursor: pointer;
}

.dashboard-panel__row:hover,
.dashboard-panel__row:focus {
    background: rgba(47, 179, 111, 0.05);
    outline: none;
}
</style>
