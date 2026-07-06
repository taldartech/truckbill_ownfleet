<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Due Services">
            <template v-if="canCreate" #actions>
                <AppButton icon="bi bi-plus" label="Add Maintenance" @click="goToCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing due services.
        </div>

        <AppIndexFiltersCard :active="filtersActive" @apply="applyFilters" @clear="clearFilters">
            <div class="maintenance-filter-search">
                <label class="form-label" :for="`${pageId}_search`">Search</label>
                <AppIndexSearchInput
                    v-model="filters.search"
                    :input-id="`${pageId}_search`"
                    placeholder="Truck, service type, provider…"
                    max-width="100%"
                    :disabled="loading"
                    @keydown.enter.prevent="applyFilters"
                />
            </div>
            <div>
                <label class="form-label" :for="`${pageId}_vehicle`">Truck</label>
                <VehicleSelect
                    :input-id="`${pageId}_vehicle`"
                    v-model="filters.vehicle_id"
                    :vehicles="vehicles"
                    placeholder="All trucks"
                    :disabled="loading"
                />
            </div>
            <div>
                <label class="form-label" :for="`${pageId}_service_type`">Service Type</label>
                <MaintenanceServiceTypeSelect
                    :input-id="`${pageId}_service_type`"
                    v-model="filters.maintenance_service_type_id"
                    :service-types="serviceTypes"
                    placeholder="All types"
                    :disabled="loading"
                />
            </div>
            <div>
                <label class="form-label">Overdue Type</label>
                <AppSelect
                    v-model="filters.overdue_reason"
                    :options="overdueReasonOptions"
                    placeholder="All overdue types"
                    search-placeholder="Search type…"
                />
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading due services…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px">#</th>
                                <th>Truck</th>
                                <th>Last Service</th>
                                <th>Service Type</th>
                                <th class="text-end">Current KM</th>
                                <th>Next Due KM</th>
                                <th>Next Due Date</th>
                                <th>Overdue</th>
                                <th v-if="hasRowActions" class="app-index-actions-cell" style="width: 140px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="records.length === 0">
                                <td :colspan="hasRowActions ? 9 : 8" class="text-center text-muted py-4">
                                    No trucks are due for service with selected filters.
                                </td>
                            </tr>
                            <tr v-for="(record, index) in records" :key="record.id">
                                <td class="text-center">{{ index + 1 }}</td>
                                <td>
                                    <RouterLink
                                        v-if="record.vehicle?.id"
                                        :to="{ name: 'trucks.show', params: { vehicleId: record.vehicle.id } }"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ vehicleLabel(record) }}
                                    </RouterLink>
                                    <span v-else>{{ vehicleLabel(record) }}</span>
                                </td>
                                <td>{{ formatDate(record.service_date) }}</td>
                                <td>{{ record.service_type_label ?? record.service_type ?? '—' }}</td>
                                <td class="text-end">{{ formatOdometer(record.vehicle?.odometer_km) }}</td>
                                <td>
                                    <span
                                        v-if="record.next_service_due_km"
                                        class="badge"
                                        :class="isKmOverdue(record) ? 'bg-warning text-dark' : 'bg-light text-dark border'"
                                    >
                                        {{ formatOdometer(record.next_service_due_km) }} km
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td>
                                    <span
                                        v-if="record.next_service_due_date"
                                        class="badge"
                                        :class="isDateOverdue(record) ? 'bg-danger' : 'bg-light text-dark border'"
                                    >
                                        {{ formatDate(record.next_service_due_date) }}
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td>
                                    <span class="badge bg-danger">{{ record.overdue_reason_label ?? '—' }}</span>
                                </td>
                                <td v-if="hasRowActions" class="app-index-actions-cell">
                                    <AppTableActions>
                                        <AppCrudActionButton
                                            v-if="canView"
                                            action="view"
                                            title="View record"
                                            @click="goToView(record)"
                                        />
                                        <AppCrudActionButton
                                            v-if="canCreate"
                                            action="edit"
                                            title="Add new service"
                                            @click="goToCreateForTruck(record)"
                                        />
                                    </AppTableActions>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { listMaintenanceServiceTypes } from '../../api/maintenanceServiceTypes';
import {
    defaultMaintenanceDueFilters,
    listDueVehicleMaintenances,
    MAINTENANCE_OVERDUE_REASON_FILTERS,
} from '../../api/vehicleMaintenances';
import MaintenanceServiceTypeSelect from '../../components/ui/MaintenanceServiceTypeSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatDate } from '../../utils/datetime';
import {
    isMaintenanceDateOverdue,
    isMaintenanceKmOverdue,
} from '../../utils/maintenanceDueStatus';

const pageId = 'maintenanceDueIndex';
const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();

const canView = computed(() => canInCompany('maintenance.view'));
const canCreate = computed(() => canInCompany('maintenance.create'));
const hasRowActions = computed(() => canView.value || canCreate.value);

const serviceTypes = ref([]);
const vehicles = ref([]);
const records = ref([]);
const loading = ref(true);

const filters = reactive(defaultMaintenanceDueFilters());
const defaultFilters = defaultMaintenanceDueFilters();
const overdueReasonOptions = MAINTENANCE_OVERDUE_REASON_FILTERS;

const filtersActive = computed(
    () =>
        filters.search.trim() !== '' ||
        filters.vehicle_id !== '' ||
        filters.maintenance_service_type_id !== '' ||
        filters.overdue_reason !== '',
);

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

function isKmOverdue(record) {
    return isMaintenanceKmOverdue(record);
}

function isDateOverdue(record) {
    return isMaintenanceDateOverdue(record);
}

function applyFilters() {
    loadRecords();
}

function clearFilters() {
    Object.assign(filters, defaultMaintenanceDueFilters());
    applyFilters();
}

async function loadRecords() {
    if (!canView.value) {
        records.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        records.value = await listDueVehicleMaintenances({
            search: filters.search.trim(),
            vehicleId: filters.vehicle_id,
            maintenanceServiceTypeId: filters.maintenance_service_type_id,
            overdueReason: filters.overdue_reason,
        });
    } catch {
        toast.error('Could not load due services.');
        records.value = [];
    } finally {
        loading.value = false;
    }
}

function goToCreate() {
    router.push({ name: 'maintenance.create' });
}

function goToCreateForTruck(record) {
    if (!record.vehicle_id) {
        return;
    }

    router.push({ name: 'maintenance.create', query: { vehicle_id: record.vehicle_id } });
}

function goToView(record) {
    router.push({ name: 'maintenance.show', params: { id: record.id } });
}

onMounted(async () => {
    if (route.query.vehicle_id) {
        filters.vehicle_id = String(route.query.vehicle_id);
    }

    try {
        const [vehicleList, typeList] = await Promise.all([listAllVehicles(), listMaintenanceServiceTypes()]);

        vehicles.value = vehicleList;
        serviceTypes.value = typeList;
    } catch {
        vehicles.value = [];
        serviceTypes.value = [];
    }

    if (canView.value) {
        loadRecords();
    } else {
        loading.value = false;
    }
});
</script>
