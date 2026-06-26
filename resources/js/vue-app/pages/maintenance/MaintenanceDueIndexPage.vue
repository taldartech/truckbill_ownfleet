<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Due Services" />

        <AppIndexListCard :loading="loading" loading-text="Loading due services…">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Truck</th>
                        <th>Last Service</th>
                        <th>Service Type</th>
                        <th class="text-end">Meter Reading</th>
                        <th>Next Due KM</th>
                        <th>Next Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="records.length === 0" class="app-index-empty-row">
                        <td colspan="7" class="text-center text-muted py-4">No trucks are due for service.</td>
                    </tr>
                    <tr v-for="(record, index) in records" :key="record.id">
                        <td class="text-center">{{ index + 1 }}</td>
                        <td>
                            <strong>{{ vehicleLabel(record) }}</strong>
                        </td>
                        <td>{{ formatDate(record.service_date) }}</td>
                        <td>{{ record.service_type_label ?? record.service_type ?? '—' }}</td>
                        <td class="text-end">{{ formatOdometer(record.odometer_km) }}</td>
                        <td>
                            <span v-if="record.next_service_due_km" class="badge bg-warning text-dark">
                                {{ formatOdometer(record.next_service_due_km) }} km
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td>
                            <span v-if="record.next_service_due_date" class="badge bg-danger">
                                {{ formatDate(record.next_service_due_date) }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { listDueVehicleMaintenances } from '../../api/vehicleMaintenances';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { formatDate } from '../../utils/datetime';

const toast = useAppToast();
const { canInCompany } = usePermissions();
const canView = computed(() => canInCompany('maintenance.view'));

const records = ref([]);
const loading = ref(true);

function vehicleLabel(record) {
    const label = record.vehicle?.registration_number ?? '—';

    if (record.vehicle?.is_active === false) {
        return `${label} (Inactive)`;
    }

    return label;
}

function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    return Number(value).toLocaleString('en-IN');
}

async function loadRecords() {
    if (!canView.value) {
        records.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        records.value = await listDueVehicleMaintenances();
    } catch {
        toast.error('Could not load due services.');
        records.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadRecords();
});
</script>
