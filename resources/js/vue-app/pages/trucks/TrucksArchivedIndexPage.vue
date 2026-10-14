<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing archived trucks.
        </div>

        <AppIndexListCard :title="pageTitle" :loading="loading" loading-text="Loading archived trucks…">
            <template #toolbar>
                <RouterLink :to="{ name: 'trucks.index' }" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left" style="color: black !important;" aria-hidden="true"></i>
                    Back to Trucks
                </RouterLink>
            </template>

            <AppIndexCollapsibleFilters panel-id="trucks-archived-filters-panel" :loading="loading"
                @search="loadVehicles(1)" @reset="clearFilters">
                <div class="filter-search-col">
                    <label class="form-label" for="trucks_archived_search">Search</label>
                    <AppIndexSearchInput v-model="filters.search" input-id="trucks_archived_search"
                        placeholder="Reg no, make, model" max-width="100%" size="md" :disabled="loading"
                        @keydown.enter="loadVehicles(1)" />
                </div>
                <div class="filter-select-col">
                    <label class="form-label">Truck Type</label>
                    <AppSelect v-model="filters.vehicle_type" :options="vehicleTypes" placeholder="All types"
                        search-placeholder="Search type…" :disabled="loading" />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px" class="text-center">S.No.</th>
                        <th>Registration</th>
                        <th>Type</th>
                        <th>Make / Model</th>
                        <th>Meter Reading (KM)</th>
                        <th>Deleted Date</th>
                        <th class="app-index-actions-cell" style="width: 100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="vehicles.length === 0" class="app-index-empty-row">
                        <td colspan="7" class="text-center text-muted py-4">
                            {{ filtersActive ? 'No archived trucks found for selected filters.' : 'No archived trucks found.' }}
                        </td>
                    </tr>

                    <tr v-for="(vehicle, index) in vehicles" :key="vehicle.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <strong class="text-dark">{{ vehicle.registration_number }}</strong>
                        </td>
                        <td>{{ vehicle.vehicle_type_label ?? vehicle.vehicle_type ?? '—' }}</td>
                        <td>{{ formatMakeModel(vehicle) }}</td>
                        <td>{{ vehicle.odometer_km !== null ? `${vehicle.odometer_km} km` : '—' }}</td>
                        <td class="small">
                            <div>{{ formatDate(vehicle.deleted_at) }}</div>
                            <div v-if="vehicle.updated_by_name" class="text-muted">
                                by {{ vehicle.updated_by_name }}
                            </div>
                        </td>
                        <td class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canRestore && vehicle.can_restore !== false"
                                    action="restore"
                                    title="Restore truck"
                                    :disabled="restoringVehicleId === vehicle.id"
                                    @click="openRestoreConfirm(vehicle)"
                                />
                                <span
                                    v-else-if="vehicle.can_restore === false"
                                    class="badge bg-secondary text-wrap"
                                    title="An active truck with this number already exists"
                                >
                                    Active exists
                                </span>
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination :pagination="pagination" :loading="loading" item-label="archived trucks"
                    aria-label="Archived trucks pagination" @page-change="goToPage" />
            </template>
        </AppIndexListCard>

        <AppConfirmModal :show="showRestoreModal" title="Restore Truck" :message="restoreConfirmMessage"
            confirm-label="Restore Truck" confirm-variant="primary" icon="fas fa-undo"
            :loading="restoringVehicleId !== null" @cancel="closeRestoreModal" @confirm="confirmRestore" />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { listArchivedVehicles, restoreVehicle } from '../../api/vehicles';
import { listAllTruckTypes } from '../../api/truckTypes';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatDate } from '../../utils/datetime';

const toast = useAppToast();
const { canInCompany } = usePermissions();
const { user, needsCompanySelection, guardCompanyContext, handleCompanyContextError } =
    useRequireCompanyContext();

const canRestore = computed(
    () => canInCompany('vehicles.delete') || canInCompany('vehicles.manage'),
);

const vehicles = ref([]);
const loading = ref(true);
const truckTypes = ref([]);
const restoringVehicleId = ref(null);
const showRestoreModal = ref(false);
const vehiclePendingRestore = ref(null);

const filters = reactive({
    search: '',
    vehicle_type: '',
});

const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);

const pageTitle = computed(() => {
    const branch = user.value?.branch_name;
    return branch ? `Archived Trucks (${branch})` : 'Archived Trucks';
});

const filtersActive = computed(() => {
    return Boolean(filters.search.trim() || filters.vehicle_type);
});

const vehicleTypes = computed(() =>
    truckTypes.value.map((type) => ({
        value: type.code,
        label: type.name,
    })),
);

const restoreConfirmMessage = computed(() => {
    if (!vehiclePendingRestore.value) {
        return '';
    }
    const reg = vehiclePendingRestore.value.registration_number;
    const branch = user.value?.branch_name;
    return branch
        ? `Are you sure you want to restore truck ${reg}? It will be active again in branch "${branch}".`
        : `Are you sure you want to restore truck ${reg}?`;
});

function rowNumber(index) {
    return (pagination.currentPage - 1) * pagination.perPage + index + 1;
}

function formatMakeModel(vehicle) {
    const parts = [vehicle.make, vehicle.model].filter(Boolean);
    return parts.length ? parts.join(' / ') : '—';
}

async function loadTruckTypes() {
    if (needsCompanySelection.value) {
        truckTypes.value = [];
        return;
    }

    try {
        truckTypes.value = await listAllTruckTypes();
    } catch {
        truckTypes.value = [];
    }
}

async function loadVehicles(page = pagination.currentPage) {
    if (needsCompanySelection.value) {
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        const response = await listArchivedVehicles({
            page,
            perPage: pagination.perPage,
            search: filters.search.trim(),
            vehicleType: filters.vehicle_type,
        });

        vehicles.value = response.data ?? [];
        syncFromMeta(response.meta, vehicles.value.length);
    } catch (error) {
        if (!handleCompanyContextError(error, 'Could not load archived trucks.')) {
            toast.error(error?.response?.data?.message ?? 'Could not load archived trucks.');
        }
        vehicles.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    filters.search = '';
    filters.vehicle_type = '';
    pagination.currentPage = 1;
    loadVehicles(1);
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }
    loadVehicles(page);
}

function openRestoreConfirm(vehicle) {
    if (!canRestore.value || restoringVehicleId.value || vehicle?.can_restore === false) {
        return;
    }
    vehiclePendingRestore.value = vehicle;
    showRestoreModal.value = true;
}

function closeRestoreModal() {
    if (restoringVehicleId.value) {
        return;
    }
    showRestoreModal.value = false;
    vehiclePendingRestore.value = null;
}

async function confirmRestore() {
    if (!vehiclePendingRestore.value || restoringVehicleId.value) {
        return;
    }

    const vehicle = vehiclePendingRestore.value;
    restoringVehicleId.value = vehicle.id;

    try {
        await restoreVehicle(vehicle.id);
        toast.success(`Truck ${vehicle.registration_number} restored successfully.`);
        showRestoreModal.value = false;
        vehiclePendingRestore.value = null;
        await loadVehicles(pagination.currentPage);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Unable to restore truck.');
    } finally {
        restoringVehicleId.value = null;
    }
}

onMounted(async () => {
    const ok = await guardCompanyContext();
    if (ok === true) {
        await Promise.all([loadTruckTypes(), loadVehicles()]);
    } else {
        loading.value = false;
    }
});
</script>
