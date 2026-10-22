<template>
    <div class="container mt-2 mt-md-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing archived trucks.
        </div>

        <AppIndexListCard :title="pageTitle" header-class="trips-card-header--gradient" :loading="loading" loading-text="Loading archived trucks…">
            <template #toolbar>
                <RouterLink
                    :to="{ name: 'trucks.index' }"
                    class="btn btn-sm btn-light border-0 bg-white shadow-sm text-dark d-inline-flex align-items-center fw-semibold py-1 px-2 rounded-2"
                    title="Back to Trucks"
                    aria-label="Back to Trucks"
                >
                    <svg class="me-1 flex-shrink-0" width="14" height="14" viewBox="0 0 16 16" fill="#000000" aria-hidden="true">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
                    </svg>
                    <span>Back<span class="d-none d-sm-inline"> to Trucks</span></span>
                </RouterLink>
            </template>

            <template #filters>
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
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px" class="text-center text-nowrap">S.No.</th>
                        <th class="text-nowrap">Registration</th>
                        <th class="text-nowrap">Type</th>
                        <th class="text-nowrap">Make / Model</th>
                        <th class="text-nowrap">Meter Reading (KM)</th>
                        <th class="text-nowrap">Deleted Date</th>
                        <th class="app-index-actions-cell text-center text-nowrap" style="width: 80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="vehicles.length === 0" class="app-index-empty-row">
                        <td colspan="7" class="text-center text-muted py-4">
                            {{ filtersActive ? 'No archived trucks found for selected filters.' : 'No archived trucks found.' }}
                        </td>
                    </tr>

                    <tr v-for="(vehicle, index) in vehicles" :key="vehicle.id">
                        <td class="text-center text-nowrap">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">
                            <strong class="text-dark">{{ vehicle.registration_number }}</strong>
                        </td>
                        <td class="text-nowrap">{{ vehicle.vehicle_type_label ?? vehicle.vehicle_type ?? '—' }}</td>
                        <td class="text-nowrap">{{ formatMakeModel(vehicle) }}</td>
                        <td class="text-nowrap">{{ vehicle.odometer_km !== null ? `${vehicle.odometer_km} km` : '—' }}</td>
                        <td class="small text-nowrap">
                            <div>{{ formatDate(vehicle.deleted_at) }}</div>
                            <div v-if="vehicle.updated_by_name" class="text-muted">
                                by {{ vehicle.updated_by_name }}
                            </div>
                        </td>
                        <td class="app-index-actions-cell text-center text-nowrap">
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

<style scoped>
/* ── Base / Desktop View ───────────────────────────────────────────── */
.filter-search-col {
    min-width: 240px;
    flex: 1 1 240px;
}

.filter-select-col {
    min-width: 180px;
    flex: 0 0 180px;
}

.filter-search-col :deep(.app-index-search),
.filter-select-col :deep(.app-select) {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.truck-header-back-btn {
    background-color: #ffffff !important;
    background: #ffffff !important;
    color: #000000 !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
}

.truck-header-back-btn:hover {
    background-color: #f8fafc !important;
    color: #000000 !important;
}

.truck-back-icon {
    width: 16px !important;
    height: 16px !important;
    color: #000000 !important;
    fill: #000000 !important;
    display: inline-block !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
}

.app-index-list-card :deep(.app-table-actions) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.app-index-list-card :deep(.app-table-actions .btn-icon) {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px;
    padding: 0 !important;
    flex-shrink: 0;
}

.app-index-list-card :deep(.app-table-actions .btn-icon i) {
    font-size: 0.8125rem;
    line-height: 1;
}

.app-index-list-card :deep(.table) {
    font-size: 0.8125rem;
}

.app-index-list-card :deep(.table th),
.app-index-list-card :deep(.table td) {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}

/* ── Tablet / Middle Screens (768px - 1199.98px) ───────────────────── */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .app-index-list-card :deep(.card-title) {
        font-size: 1.1rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        justify-content: flex-end !important;
        margin-top: 0.25rem !important;
        margin-left: 0 !important;
    }

    .app-index-list-card :deep(.table) {
        font-size: 0.78rem !important;
    }

    .app-index-list-card :deep(.table th),
    .app-index-list-card :deep(.table td) {
        padding: 0.45rem 0.5rem !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon) {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon i) {
        font-size: 0.75rem !important;
    }
}

/* ── Mobile Devices (< 768px) ─────────────────────────────────────── */
@media (max-width: 767.98px) {
    .app-index-list-card :deep(.card-header),
    :deep(.trips-card-header--gradient) {
        padding: 0.5rem 0.75rem !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.4rem !important;
    }

    .app-index-list-card :deep(.card-title),
    :deep(.trips-card-header--gradient .card-title) {
        font-size: 0.88rem !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        margin-bottom: 0 !important;
        line-height: 1.2 !important;
        letter-spacing: -0.01em;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    :deep(.app-index-collapsible-filters) {
        padding: 0.65rem 0.75rem !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .app-index-list-card :deep(.table-responsive) {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        gap: 8px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    :deep(.app-index-filters-fields > div),
    .filter-search-col,
    .filter-select-col {
        flex: 0 0 calc(50% - 4px) !important;
        width: calc(50% - 4px) !important;
        max-width: calc(50% - 4px) !important;
        min-width: 0 !important;
    }

    /* Filter inputs compact & responsive text sizing on mobile */
    :deep(.app-index-collapsible-filters .form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    :deep(.app-index-collapsible-filters .form-control),
    :deep(.app-index-collapsible-filters .select-trigger) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder),
    :deep(.app-index-collapsible-filters .select-trigger-label) {
        font-size: 0.75rem !important;
    }

    :deep(.select-trigger-icon) {
        font-size: 0.65rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 8px !important;
        margin-top: 0.35rem !important;
        margin-left: 0 !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.75rem !important;
        height: 28px !important;
        min-height: 28px !important;
        padding: 0.15rem 0.5rem !important;
        line-height: 1 !important;
        border-radius: 6px !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn i) {
        font-size: 0.7rem !important;
        margin-right: 0.25rem !important;
    }

    /* Table micro adjustments on mobile */
    .app-index-list-card :deep(.table) {
        font-size: 0.72rem !important;
    }

    .app-index-list-card :deep(.table th) {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
    }

    .app-index-list-card :deep(.table td) {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.72rem !important;
    }

    .app-index-list-card :deep(.app-table-actions) {
        gap: 4px !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon) {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        border-radius: 0.25rem !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon i) {
        font-size: 0.6875rem !important;
    }

    /* Pagination responsive on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.4rem !important;
        padding: 0.5rem 0.6rem !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.72rem !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: 0 !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.2rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

/* ── Extra-Small Mobile Devices (< 380px) ─────────────────────────── */
@media (max-width: 380px) {
    .app-index-list-card :deep(.card-title),
    :deep(.trips-card-header--gradient .card-title) {
        font-size: 0.8rem !important;
    }
}
</style>
