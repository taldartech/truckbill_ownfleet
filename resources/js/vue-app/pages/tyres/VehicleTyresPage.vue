<template>
    <div class="container mt-4">
        <RouterLink :to="{ name: 'trucks.index' }" class="small text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Trucks
        </RouterLink>
        <h3 class="mt-2 mb-3">Tyre Positions — {{ vehicleLabel }}</h3>

        <div v-if="loading" class="text-muted py-4 text-center">Loading truck tyre map…</div>
        <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

        <div v-else class="row g-3">
            <div v-for="slot in positions" :key="slot.position" class="col-md-6 col-lg-4">
                <div class="card h-100" :class="slot.is_empty ? 'border-dashed' : 'border-success'">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong>{{ slot.position_label }}</strong>
                        <span v-if="slot.is_spare" class="badge bg-info">Spare</span>
                    </div>
                    <div class="card-body small">
                        <template v-if="slot.tyre">
                            <div class="fw-semibold">
                                <RouterLink :to="{ name: 'tyres.show', params: { id: slot.tyre.id } }">
                                    {{ slot.tyre.serial_number }}
                                </RouterLink>
                            </div>
                            <div class="text-muted">{{ slot.tyre.brand || '—' }}</div>
                            <div class="mt-2">
                                Installed: {{ slot.tyre.installed_at }} @ {{ slot.tyre.km_at_installation }} km
                            </div>
                            <div>Running on truck: <strong>{{ slot.tyre.stint_km }} km</strong></div>
                            <div v-if="slot.tyre.remaining_life_km != null" class="mt-1">
                                Life left: <strong>{{ slot.tyre.remaining_life_km }} km</strong>
                                ({{ slot.tyre.life_used_percent }}% used)
                            </div>
                            <div class="progress mt-2" style="height: 6px">
                                <div
                                    class="progress-bar"
                                    :class="lifeBarClass(slot.tyre.life_used_percent)"
                                    :style="{ width: Math.min(100, slot.tyre.life_used_percent || 0) + '%' }"
                                ></div>
                            </div>
                        </template>
                        <span v-else class="text-muted">Empty — no tyre</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { getVehicleTyrePositions } from '../../api/tyres';

const route = useRoute();
const positions = ref([]);
const loading = ref(true);
const error = ref('');
const vehicleLabel = ref('');

function lifeBarClass(percent) {
    if (percent >= 85) {
        return 'bg-danger';
    }
    if (percent >= 60) {
        return 'bg-warning';
    }
    return 'bg-success';
}

onMounted(async () => {
    try {
        const vehicles = await listAllVehicles();
        const vehicle = vehicles.find((v) => v.id === route.params.vehicleId);
        vehicleLabel.value = vehicle?.registration_number ?? route.params.vehicleId;
        positions.value = await getVehicleTyrePositions(route.params.vehicleId);
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Could not load tyre positions for this truck.';
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.border-dashed {
    border-style: dashed !important;
}
</style>
