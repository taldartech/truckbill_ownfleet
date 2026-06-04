<template>
    <div class="container mt-4 trips-index-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Trip Management</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" label="Create Trip" @click="goToCreate" />
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before managing trips.
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card mb-3">
            <div class="card-header py-2">
                <span class="small text-muted">Filters</span>
                <span v-if="filtersActive" class="badge bg-light text-dark border ms-2">Active</span>
            </div>
            <div class="card-body row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input v-model="filters.from_date" type="date" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input v-model="filters.to_date" type="date" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vehicle</label>
                    <select v-model="filters.vehicle_id" class="form-select">
                        <option value="">All vehicles</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.registration_number }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select v-model="filters.status" class="form-select">
                        <option value="">All statuses</option>
                        <option v-for="s in tripStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" @click="loadTrips(1)">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                    <button type="button" class="btn btn-outline-secondary" @click="clearFilters">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Trips List</h3>
                <span v-if="pagination.total" class="text-muted small">
                    {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                </span>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading trips…</div>
                <table v-else class="table table-bordered table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Trip #</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Distance (F / H / E)</th>
                            <th>Freight</th>
                            <th>Expense</th>
                            <th>Profit</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="trips.length === 0">
                            <td :colspan="canManage ? 10 : 9" class="text-center text-muted py-4">
                                No trips found for selected filters.
                            </td>
                        </tr>
                        <tr v-for="trip in trips" :key="trip.id">
                            <td>
                                <strong>{{ trip.trip_number || '—' }}</strong>
                                <div class="small text-muted">{{ formatDate(trip.start_at) }}</div>
                            </td>
                            <td>{{ trip.vehicle?.registration_number ?? '—' }}</td>
                            <td>{{ trip.driver?.name ?? '—' }}</td>
                            <td class="small">{{ formatRoute(trip) }}</td>
                            <td>
                                <span class="badge" :class="statusBadge(trip.status).badge">
                                    {{ statusBadge(trip.status).label }}
                                </span>
                            </td>
                            <td>
                                {{ trip.total_distance_km }} KM
                                <div class="small text-muted">
                                    F: {{ trip.loaded_distance_km }} / H: {{ trip.half_loaded_distance_km ?? 0 }} / E:
                                    {{ trip.empty_distance_km }}
                                </div>
                            </td>
                            <td>₹{{ formatMoney(trip.total_freight_amount) }}</td>
                            <td>₹{{ formatMoney(trip.total_expense_amount) }}</td>
                            <td :class="Number(trip.profit_amount) >= 0 ? 'text-success' : 'text-danger'">
                                ₹{{ formatMoney(trip.profit_amount) }}
                            </td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="goToEdit(trip)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(trip)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="pagination.lastPage > 1" class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }}
                </small>
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loading || pagination.currentPage <= 1"
                        @click="loadTrips(pagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loading || pagination.currentPage >= pagination.lastPage"
                        @click="loadTrips(pagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { deleteTrip, defaultTripListFilters, listTrips, TRIP_STATUSES, tripStatusMeta } from '../../api/trips';
import { listAllVehicles } from '../../api/vehicles';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('trips.manage'));

const { applyApiError } = useApiFormErrors();

const trips = ref([]);
const vehicles = ref([]);
const loading = ref(true);
const flash = reactive({ success: '', error: '' });
const tripStatuses = TRIP_STATUSES;

const filters = reactive(defaultTripListFilters());

const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0,
});

const defaultFilters = defaultTripListFilters();

const filtersActive = computed(() => {
    return (
        filters.from_date !== defaultFilters.from_date ||
        filters.to_date !== defaultFilters.to_date ||
        filters.vehicle_id !== '' ||
        filters.status !== ''
    );
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-IN');
}

function statusBadge(status) {
    return tripStatusMeta(status);
}

function formatRoute(trip) {
    const legs = trip.route_legs ?? [];

    if (legs.length === 0) {
        return '—';
    }

    const points = [];

    if (legs[0]?.from_city) {
        points.push(legs[0].from_city);
    }

    legs.forEach((leg) => {
        if (leg.to_city) {
            points.push(leg.to_city);
        }
    });

    return points.length > 0 ? points.join(' → ') : '—';
}

function showRouteFlash() {
    if (route.query.created === '1') {
        flash.success = 'Trip created successfully.';
        toast.success(flash.success);
    } else if (route.query.updated === '1') {
        flash.success = 'Trip updated successfully.';
        toast.success(flash.success);
    } else if (route.query.denied === '1') {
        flash.error = 'You do not have permission to open that page, or company context is missing.';
        toast.error(flash.error);
    }
}

function goToCreate() {
    if (needsCompanySelection.value) {
        flash.error = 'Select and enter a company before creating a trip.';
        toast.warning(flash.error);

        return;
    }

    if (!canManage.value) {
        flash.error = 'You do not have permission to create trips.';
        toast.error(flash.error);

        return;
    }

    router.push({ name: 'trips.create' });
}

async function loadLookups() {
    vehicles.value = await listAllVehicles();
}

function clearFilters() {
    Object.assign(filters, defaultTripListFilters());
    loadTrips(1);
}

async function loadTrips(page = 1) {
    loading.value = true;
    flash.error = '';

    try {
        const result = await listTrips({
            page,
            perPage: 15,
            from_date: filters.from_date || undefined,
            to_date: filters.to_date || undefined,
            vehicle_id: filters.vehicle_id || undefined,
            status: filters.status || undefined,
        });

        trips.value = result.data;
        pagination.currentPage = result.meta.current_page ?? page;
        pagination.lastPage = result.meta.last_page ?? 1;
        pagination.from = result.meta.from ?? 0;
        pagination.to = result.meta.to ?? 0;
        pagination.total = result.meta.total ?? trips.value.length;
    } catch (error) {
        flash.error = applyApiError(error, 'Failed to load trips.');
    } finally {
        loading.value = false;
    }
}

function goToEdit(trip) {
    router.push({ name: 'trips.edit', params: { id: trip.id } });
}

async function handleDelete(trip) {
    if (!window.confirm(`Delete trip ${trip.trip_number || trip.id}?`)) {
        return;
    }

    try {
        await deleteTrip(trip.id);
        flash.success = 'Trip deleted.';
        await loadTrips(pagination.currentPage);
    } catch (error) {
        flash.error = applyApiError(error, 'Failed to delete trip.');
    }
}

watch(
    () => route.query,
    () => showRouteFlash(),
    { immediate: true },
);

onMounted(async () => {
    showRouteFlash();
    await loadLookups();
    await loadTrips();
});
</script>
