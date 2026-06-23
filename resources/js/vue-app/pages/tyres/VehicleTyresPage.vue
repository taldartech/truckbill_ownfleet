<template>
    <div class="container mt-4 vehicle-tyres-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="{ name: 'trucks.index' }" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Trucks
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">Tyre Positions</h3>
                    <span v-if="vehicleLabel" class="badge bg-secondary">{{ vehicleLabel }}</span>
                </div>
                <p v-if="vehicle" class="text-muted small mb-0">
                    {{ vehicle.vehicle_type_label ?? vehicle.vehicle_type }}
                    <span v-if="vehicle.make || vehicle.model"> · {{ formatMakeModel(vehicle) }}</span>
                </p>
            </div>
            <RouterLink
                v-if="vehicle"
                :to="{ name: 'trucks.show', params: { vehicleId: vehicle.id } }"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-truck"></i> Truck Details
            </RouterLink>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading truck tyre data…</div>
        <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-primary">
                        <div class="card-body py-3">
                            <div class="text-muted small">Tyres Used (Total)</div>
                            <div class="fs-4 fw-bold text-primary">{{ summary.unique_tyres_used ?? 0 }}</div>
                            <div class="small text-muted">unique tyres on this truck</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-success">
                        <div class="card-body py-3">
                            <div class="text-muted small">Currently Installed</div>
                            <div class="fs-4 fw-bold text-success">{{ summary.currently_installed ?? 0 }}</div>
                            <div class="small text-muted">
                                of {{ summary.total_positions ?? positions.length }} positions
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-warning">
                        <div class="card-body py-3">
                            <div class="text-muted small">Empty Positions</div>
                            <div class="fs-4 fw-bold text-warning">{{ summary.empty_positions ?? 0 }}</div>
                            <div class="small text-muted">need tyres</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-info">
                        <div class="card-body py-3">
                            <div class="text-muted small">Total KM on Truck</div>
                            <div class="fs-4 fw-bold text-info">{{ formatKm(summary.total_km_on_truck) }}</div>
                            <div class="small text-muted">{{ summary.total_stints ?? 0 }} install stints</div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === 'positions' }"
                        @click="activeTab = 'positions'"
                    >
                        <i class="bi bi-grid-3x3-gap me-1"></i> Current Positions
                    </button>
                </li>
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === 'history' }"
                        @click="activeTab = 'history'"
                    >
                        <i class="bi bi-clock-history me-1"></i> Tyre History
                        <span v-if="history.length" class="badge bg-secondary ms-1">{{ history.length }}</span>
                    </button>
                </li>
            </ul>

            <div v-if="activeTab === 'positions'" class="positions-layout">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header py-2">
                                <strong>Truck Chassis View</strong>
                                <span class="text-muted small ms-2">— green = tyre on, dashed = free</span>
                            </div>
                            <div class="card-body">
                                <TruckTyreChassis :positions="positions" />
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header py-2"><strong>Installed Tyres</strong></div>
                            <div class="card-body p-0">
                                <ul v-if="installedSlots.length" class="list-group list-group-flush small">
                                    <li
                                        v-for="slot in installedSlots"
                                        :key="slot.position"
                                        class="list-group-item"
                                    >
                                        <div class="fw-semibold">{{ slot.position_label }}</div>
                                        <RouterLink
                                            :to="{ name: 'tyres.show', params: { id: slot.tyre.id } }"
                                            class="text-decoration-none"
                                        >
                                            {{ slot.tyre.serial_number }}
                                        </RouterLink>
                                        <span class="text-muted"> · {{ slot.tyre.brand || '—' }}</span>
                                        <div class="text-muted mt-1">{{ formatKm(slot.tyre.stint_km) }} on truck</div>
                                        <div
                                            v-if="slot.tyre.remaining_life_km != null"
                                            class="progress mt-1"
                                            style="height: 5px"
                                        >
                                            <div
                                                class="progress-bar"
                                                :class="lifeBarClass(slot.tyre.life_used_percent)"
                                                :style="{
                                                    width: Math.min(100, slot.tyre.life_used_percent || 0) + '%',
                                                }"
                                            ></div>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="text-muted small mb-0 p-3">No tyres installed on this truck.</p>
                            </div>
                        </div>

                        <div v-if="spareSlots.length" class="card mt-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>Spare Tyres</strong>
                                <span class="badge bg-info text-dark">{{ spareSlots.length }}</span>
                            </div>
                            <div class="card-body py-2 small">
                                <ul class="list-group list-group-flush">
                                    <li
                                        v-for="slot in spareSlots"
                                        :key="slot.position"
                                        class="list-group-item px-0 d-flex justify-content-between align-items-center"
                                    >
                                        <span>{{ slot.position_label }}</span>
                                        <span v-if="slot.tyre" class="badge bg-success">
                                            {{ slot.tyre.serial_number }}
                                        </span>
                                        <span v-else class="badge bg-light text-muted border">Free</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-if="emptyRoadSlots.length" class="card mt-3">
                            <div class="card-header py-2">
                                <strong>Free Road Positions</strong>
                                <span class="badge bg-warning text-dark ms-1">{{ emptyRoadSlots.length }}</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="d-flex flex-wrap gap-1">
                                    <span
                                        v-for="slot in emptyRoadSlots"
                                        :key="slot.position"
                                        class="badge bg-light text-muted border"
                                    >
                                        {{ slot.position_label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Truck Tyre History</strong>
                    <span class="text-muted small">
                        {{ summary.unique_tyres_used ?? 0 }} unique tyres · {{ summary.total_stints ?? 0 }} stints
                    </span>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tyre</th>
                                <th>Position</th>
                                <th>Installed</th>
                                <th>Removed</th>
                                <th class="text-end">KM on Truck</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="history.length === 0">
                                <td colspan="6" class="text-center text-muted py-4">
                                    No tyre history for this truck yet.
                                </td>
                            </tr>
                            <tr v-for="row in history" :key="row.id" :class="{ 'table-success': row.is_active }">
                                <td>
                                    <RouterLink
                                        v-if="row.tyre_id"
                                        :to="{ name: 'tyres.show', params: { id: row.tyre_id } }"
                                        class="fw-semibold text-decoration-none"
                                    >
                                        {{ row.tyre_serial_number }}
                                    </RouterLink>
                                    <div class="text-muted small">{{ row.tyre_brand || '—' }}</div>
                                </td>
                                <td>{{ row.position_label }}</td>
                                <td>{{ formatDate(row.installed_at) }}</td>
                                <td>
                                    <span v-if="row.is_active" class="badge bg-success">On truck</span>
                                    <span v-else>{{ formatDate(row.removed_at) }}</span>
                                </td>
                                <td class="text-end fw-semibold">{{ formatKm(row.stint_km) }}</td>
                                <td>
                                    <span class="badge" :class="tyreStatusBadgeClass(row.tyre_status)">
                                        {{ formatTyreStatus(row.tyre_status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { fetchVehicle } from '../../api/vehicles';
import { getVehicleTyreHistory, getVehicleTyrePositions } from '../../api/tyres';
import TruckTyreChassis from '../../components/tyres/TruckTyreChassis.vue';
import { formatDate } from '../../utils/datetime';

const route = useRoute();
const positions = ref([]);
const history = ref([]);
const summary = ref({});
const loading = ref(true);
const error = ref('');
const vehicleLabel = ref('');
const vehicle = ref(null);
const activeTab = ref('positions');

const installedSlots = computed(() => positions.value.filter((slot) => !slot.is_empty && slot.tyre));
const spareSlots = computed(() => positions.value.filter((slot) => slot.is_spare));
const emptyRoadSlots = computed(() => positions.value.filter((slot) => slot.is_empty && !slot.is_spare));

function formatMakeModel(v) {
    return [v.make, v.model].filter(Boolean).join(' ');
}

function formatKm(value) {
    if (value == null || value === '') {
        return '—';
    }
    return `${Number(value).toLocaleString('en-IN')} km`;
}

function lifeBarClass(percent) {
    if (percent >= 85) {
        return 'bg-danger';
    }
    if (percent >= 60) {
        return 'bg-warning';
    }
    return 'bg-success';
}

function formatTyreStatus(status) {
    if (!status) {
        return '—';
    }
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function tyreStatusBadgeClass(status) {
    const map = {
        INSTALLED: 'bg-success',
        IN_STOCK: 'bg-secondary',
        SCRAPPED: 'bg-dark',
    };
    return map[status] ?? 'bg-secondary';
}

function buildSummaryFromPositions(positionSlots, historyRows = []) {
    const installed = positionSlots.filter((slot) => !slot.is_empty && slot.tyre);
    const uniqueFromHistory = new Set(historyRows.map((row) => row.tyre_id).filter(Boolean));
    const uniqueFromPositions = new Set(installed.map((slot) => slot.tyre.id));

    return {
        unique_tyres_used: uniqueFromHistory.size || uniqueFromPositions.size,
        total_stints: historyRows.length || installed.length,
        currently_installed: installed.length,
        empty_positions: positionSlots.filter((slot) => slot.is_empty).length,
        total_positions: positionSlots.length,
        total_km_on_truck: installed.reduce((sum, slot) => sum + (Number(slot.tyre?.stint_km) || 0), 0),
    };
}

onMounted(async () => {
    try {
        const vehicleId = route.params.vehicleId;
        const [vehicleData, positionData] = await Promise.all([
            fetchVehicle(vehicleId),
            getVehicleTyrePositions(vehicleId),
        ]);
        vehicle.value = vehicleData;
        vehicleLabel.value = vehicleData?.registration_number ?? vehicleId;
        positions.value = positionData;

        try {
            const historyData = await getVehicleTyreHistory(vehicleId);
            history.value = historyData.history;
            summary.value =
                historyData.history.length > 0 || historyData.summary?.total_stints > 0
                    ? historyData.summary
                    : buildSummaryFromPositions(positionData, historyData.history);
        } catch {
            history.value = [];
            summary.value = buildSummaryFromPositions(positionData);
        }
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Could not load tyre data for this truck.';
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.stat-card {
    transition: box-shadow 0.15s ease;
}

.stat-card:hover {
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
}
</style>
