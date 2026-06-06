<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Trip Management">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="goToCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before managing trips.
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadTrips(1)" @clear="clearFilters">
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
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">Trips List</h3>
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
            <AppIndexPaginationFooter
                page-id="tripsIndex"
                :loading="loading"
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :per-page="pagination.perPage"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
                item-label="trips"
                :per-page-options="[15, 25, 50]"
                @page-change="loadTrips"
                @update:per-page="onPerPageUpdate"
            />
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
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useListPagination } from '../../composables/useListPagination';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { friendlyApiErrorMessage } from '../../utils/formErrors';

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

const { pagination, syncFromMeta } = useListPagination(15);

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
    const { created, updated, denied, ...rest } = route.query;

    if (created === '1') {
        flash.success = 'Trip created successfully.';
        toast.success(flash.success);
        router.replace({ query: rest });

        return;
    }

    if (updated === '1') {
        flash.success = 'Trip updated successfully.';
        toast.success(flash.success);
        router.replace({ query: rest });

        return;
    }

    if (denied === '1') {
        flash.error = 'You do not have permission to open that page, or company context is missing.';
        toast.error(flash.error);
        router.replace({ query: rest });
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

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    loadTrips(1);
}

async function loadTrips(page = 1) {
    loading.value = true;
    flash.error = '';

    try {
        const result = await listTrips({
            page,
            perPage: pagination.perPage,
            from_date: filters.from_date || undefined,
            to_date: filters.to_date || undefined,
            vehicle_id: filters.vehicle_id || undefined,
            status: filters.status || undefined,
        });

        trips.value = result.data;
        syncFromMeta(result.meta, trips.value.length);
    } catch (error) {
        if (!applyApiError(error, 'Failed to load trips.')) {
            flash.error = friendlyApiErrorMessage(error, 'Failed to load trips.');
            toast.error(flash.error);
        }
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
        if (!applyApiError(error, 'Failed to delete trip.')) {
            flash.error = friendlyApiErrorMessage(error, 'Failed to delete trip.');
            toast.error(flash.error);
        }
    }
}

watch(
    () => route.query,
    () => showRouteFlash(),
    { immediate: true },
);

onMounted(async () => {
    await loadLookups();
    await loadTrips();
});
</script>
