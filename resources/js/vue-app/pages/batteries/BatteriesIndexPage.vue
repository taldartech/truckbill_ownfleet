<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing batteries.
        </div>

        <div class="row g-2 mb-3">
            <div
                v-for="pill in visiblePills"
                :key="pill.key"
                class="col-6 col-md-4 col-xl"
            >
                <div
                    class="battery-kpi"
                    :class="{ 'battery-kpi--active': isPillActive(pill) }"
                    role="button"
                    tabindex="0"
                    :aria-pressed="isPillActive(pill)"
                    @click="selectPill(pill)"
                    @keydown.enter.prevent="selectPill(pill)"
                    @keydown.space.prevent="selectPill(pill)"
                >
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="battery-kpi__label">{{ pill.label }}</span>
                        <i :class="[pill.icon, pill.textClass]" aria-hidden="true"></i>
                    </div>
                    <div class="battery-kpi__value" :class="pill.textClass">
                        <span v-if="countsLoading" class="placeholder col-4 placeholder-glow rounded"></span>
                        <template v-else>{{ Number(counts[pill.key] || 0).toLocaleString('en-IN') }}</template>
                    </div>
                </div>
            </div>
        </div>

        <AppIndexListCard title="Battery Management" :loading="loading" loading-text="Loading batteries…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="batteries-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                    <span v-if="activeFilterCount" class="badge bg-primary ms-1">{{ activeFilterCount }}</span>
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Battery"
                    @click="openCreate"
                >
                    Add Battery
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="batteries-filters-panel"
                :loading="loading"
                @search="applyFilters"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="batteryIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="batteryIndex_search"
                        placeholder="Serial no, brand, model, supplier…"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter.prevent="applyFilters"
                    />
                </div>
                <div>
                    <label class="form-label" for="batteryIndex_status">Status</label>
                    <AppSelect
                        v-model="statusFilter"
                        input-id="batteryIndex_status"
                        :options="statusOptions"
                        placeholder="All statuses"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label" for="batteryIndex_brand">Brand</label>
                    <AppSelect
                        v-model="brandFilter"
                        input-id="batteryIndex_brand"
                        :options="brandOptions"
                        placeholder="All brands"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label" for="batteryIndex_warranty">Warranty</label>
                    <AppSelect
                        v-model="warrantyFilter"
                        input-id="batteryIndex_warranty"
                        :options="warrantyOptions"
                        placeholder="Any warranty"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label" for="batteryIndex_vehicle">Truck</label>
                    <VehicleSelect
                        v-model="vehicleFilter"
                        input-id="batteryIndex_vehicle"
                        :vehicles="vehicles"
                        placeholder="All trucks"
                        :disabled="loading"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                    <thead class="table-light">
                        <tr>
                            <th>Battery #</th>
                            <th>Brand / Specs</th>
                            <th>Status</th>
                            <th>Truck / Slot</th>
                            <th class="text-end">Current Stint</th>
                            <th>Warranty</th>
                            <th>Purchase</th>
                            <th class="app-index-actions-cell" style="width: 200px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && batteries.length === 0" class="app-index-empty-row">
                            <td colspan="8" class="text-center text-muted py-4">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Loading batteries…
                            </td>
                        </tr>
                        <tr v-else-if="loadError" class="app-index-empty-row">
                            <td colspan="8" class="text-center py-4">
                                <div class="text-danger mb-2">
                                    <i class="fas fa-triangle-exclamation me-1"></i>{{ loadError }}
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="fetchBatteries">
                                    Retry
                                </button>
                            </td>
                        </tr>
                        <tr v-else-if="batteries.length === 0" class="app-index-empty-row">
                            <td colspan="8" class="text-center text-muted py-4">
                                <template v-if="activeFilterCount">No batteries match the selected filters.</template>
                                <template v-else>
                                    No batteries yet.
                                    <span v-if="canCreate">Click <strong>Add Battery</strong> to add your first one.</span>
                                </template>
                            </td>
                        </tr>
                        <tr v-for="b in batteries" :key="b.id">
                            <td>
                                <RouterLink
                                    :to="{ name: 'batteries.show', params: { id: b.id } }"
                                    class="fw-semibold text-decoration-none text-dark"
                                >
                                    <i class="fas fa-car-battery text-warning me-1"></i>
                                    {{ b.serial_number }}
                                </RouterLink>
                                <div class="text-muted small">{{ b.battery_type_label || batteryTypeLabel(b.battery_type) }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    {{ b.brand }}<span v-if="b.model" class="text-muted fw-normal"> · {{ b.model }}</span>
                                </div>
                                <span class="badge bg-light text-dark border">{{ Number(b.capacity_ah) }} Ah</span>
                                <span class="badge bg-light text-secondary border ms-1">{{ b.voltage }}</span>
                            </td>
                            <td>
                                <span class="badge" :class="batteryStatusBadgeClass(b.status)">
                                    {{ batteryStatusLabel(b.status) }}
                                </span>
                            </td>
                            <td>
                                <template v-if="b.active_installation">
                                    <RouterLink
                                        :to="{ name: 'trucks.show', params: { vehicleId: b.active_installation.vehicle_id } }"
                                        class="fw-semibold text-decoration-none"
                                    >
                                        <i class="fas fa-truck me-1"></i>
                                        {{ b.active_installation.vehicle_registration || 'Truck' }}
                                    </RouterLink>
                                    <div class="text-muted small">
                                        Slot {{ b.active_installation.slot_name || '—' }}
                                        · since {{ formatDate(b.active_installation.installed_at) }}
                                    </div>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td class="text-end">
                                <template v-if="b.active_installation">
                                    <span class="fw-semibold">{{ formatKm(b.active_installation.stint_km) }}</span>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <span class="badge" :class="batteryWarrantyMeta(b.warranty_status).badgeClass">
                                    {{ batteryWarrantyMeta(b.warranty_status).label }}
                                </span>
                                <div v-if="b.warranty_valid_until" class="text-muted small mt-1">
                                    till {{ formatDate(b.warranty_valid_until) }}
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    {{ b.purchase_cost ? formatMoney(b.purchase_cost) : '—' }}
                                </div>
                                <div v-if="b.purchase_date" class="text-muted small">
                                    {{ formatDate(b.purchase_date) }}
                                </div>
                            </td>
                            <td class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="view" @click="goToDetail(b.id)" />
                                    <AppCrudActionButton v-if="canEdit && b.status !== 'SCRAPPED'" action="edit" @click="openEdit(b)" />
                                    <button
                                        v-if="canCreate && (b.status === 'IN_STOCK' || b.status === 'REMOVED')"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-success"
                                        title="Install on truck"
                                        aria-label="Install on truck"
                                        @click="openInstall(b)"
                                    >
                                        <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        v-if="canCreate && b.active_installation"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-primary"
                                        title="Replace battery"
                                        aria-label="Replace battery"
                                        @click="openReplace(b)"
                                    >
                                        <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        v-if="canCreate && b.active_installation"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-warning"
                                        title="Remove from truck"
                                        aria-label="Remove from truck"
                                        @click="openRemove(b)"
                                    >
                                        <i class="fas fa-plug-circle-minus" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        v-if="canEdit && b.status !== 'SCRAPPED'"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-info"
                                        title="Log health check"
                                        aria-label="Log health check"
                                        @click="openInspection(b)"
                                    >
                                        <i class="fas fa-stethoscope" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        v-if="canEdit && !b.active_installation && b.status !== 'SCRAPPED'"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-danger"
                                        title="Scrap battery"
                                        aria-label="Scrap battery"
                                        @click="openScrap(b)"
                                    >
                                        <i class="fas fa-recycle" aria-hidden="true"></i>
                                    </button>
                                    <AppCrudActionButton
                                        v-if="canDelete && !b.active_installation && b.status !== 'SCRAPPED'"
                                        action="delete"
                                        @click="promptDelete(b)"
                                    />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <template #footer>
                <AppIndexPaginationFooter
                    page-id="batteries-index"
                    :loading="loading"
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :per-page="pagination.perPage"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                    item-label="batteries"
                    @page-change="onPageChange"
                    @update:per-page="onPerPageChange"
                />
            </template>
        </AppIndexListCard>

        <BatteryFormModal
            :show="showFormModal"
            :battery="selectedBattery"
            :brands="brands"
            @close="showFormModal = false"
            @saved="onSaved"
        />

        <BatteryInstallModal
            :show="showInstallModal"
            :battery="selectedBattery"
            @close="showInstallModal = false"
            @installed="onActionDone('Battery installed on truck.')"
        />

        <BatteryReplaceModal
            :show="showReplaceModal"
            :battery="selectedBattery"
            @close="showReplaceModal = false"
            @replaced="onActionDone('Battery replaced successfully.')"
        />

        <BatteryRemoveModal
            :show="showRemoveModal"
            :installation="selectedBattery?.active_installation"
            :battery-serial="selectedBattery?.serial_number"
            @close="showRemoveModal = false"
            @removed="onActionDone('Battery removed from truck and moved to standby.')"
        />

        <BatteryInspectionModal
            :show="showInspectionModal"
            :battery="selectedBattery"
            @close="showInspectionModal = false"
            @saved="toast.success('Health check saved.')"
        />

        <BatteryScrapModal
            :show="showScrapModal"
            :battery="selectedBattery"
            @close="showScrapModal = false"
            @scrapped="onActionDone('Battery marked as scrapped.')"
        />

        <AppConfirmModal
            :show="showDeleteConfirm"
            title="Delete Battery"
            :loading="deleting"
            danger
            confirm-button-text="Delete"
            @close="showDeleteConfirm = false"
            @confirm="confirmDelete"
        >
            Are you sure you want to delete battery
            <strong>{{ batteryToDelete?.serial_number }}</strong>?
            This removes it from the battery register.
        </AppConfirmModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { listBatteries, getBatteryCounts, deleteBattery } from '../../api/batteries';
import { listAllVehicles } from '../../api/vehicles';
import { formatDate } from '../../utils/datetime';
import { formatMoney } from '../../utils/formatMoney';
import {
    BATTERY_STATUS_META,
    BATTERY_STATUS_OPTIONS,
    batteryStatusBadgeClass,
    batteryStatusLabel,
    batteryTypeLabel,
    batteryWarrantyMeta,
    formatKm,
} from '../../constants/battery';

import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';

import BatteryFormModal from '../../components/batteries/BatteryFormModal.vue';
import BatteryInstallModal from '../../components/batteries/BatteryInstallModal.vue';
import BatteryReplaceModal from '../../components/batteries/BatteryReplaceModal.vue';
import BatteryRemoveModal from '../../components/batteries/BatteryRemoveModal.vue';
import BatteryInspectionModal from '../../components/batteries/BatteryInspectionModal.vue';
import BatteryScrapModal from '../../components/batteries/BatteryScrapModal.vue';
import '../../components/batteries/battery.css';

const router = useRouter();
const route = useRoute();
const toast = useAppToast();
const { user, canAnyInCompany, canCreateInCompany, canEditInCompany, canDeleteRecords } = usePermissions();

const needsCompanySelection = computed(() => user.value?.requires_company_selection === true);
const canCreate = computed(() => canCreateInCompany('batteries'));
const canEdit = computed(() => canEditInCompany('batteries'));
const canDelete = computed(() => canDeleteRecords.value && canAnyInCompany(['batteries.delete', 'batteries.manage']));

const loading = ref(false);
const loadError = ref('');
const countsLoading = ref(false);
const filtersOpen = ref(false);
const batteries = ref([]);
const vehicles = ref([]);
const brands = ref([]);

const search = ref('');
const statusFilter = ref('');
const brandFilter = ref('');
const warrantyFilter = ref('');
const vehicleFilter = ref('');

const counts = ref({});

const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    from: 0,
    to: 0,
});

const statusPill = (key, status) => ({
    key,
    status,
    warranty: '',
    label: BATTERY_STATUS_META[status].label,
    icon: BATTERY_STATUS_META[status].icon,
    textClass: BATTERY_STATUS_META[status].textClass,
});

const pills = [
    { key: 'total', status: '', warranty: '', label: 'All Batteries', icon: 'fas fa-layer-group', textClass: 'text-dark' },
    statusPill('in_stock', 'IN_STOCK'),
    statusPill('installed', 'INSTALLED'),
    statusPill('removed', 'REMOVED'),
    { ...statusPill('under_inspection', 'UNDER_INSPECTION'), hideWhenZero: true },
    { ...statusPill('returned', 'RETURNED'), hideWhenZero: true },
    statusPill('scrapped', 'SCRAPPED'),
    { key: 'warranty_expiring', status: '', warranty: 'expiring', label: 'Warranty Expiring', icon: 'fas fa-hourglass-half', textClass: 'text-danger' },
];

const visiblePills = computed(() =>
    pills.filter((pill) => !pill.hideWhenZero || Number(counts.value[pill.key] || 0) > 0),
);

const statusOptions = [{ value: '', label: 'All Statuses' }, ...BATTERY_STATUS_OPTIONS];

const warrantyOptions = [
    { value: '', label: 'Any warranty' },
    { value: 'active', label: 'In warranty' },
    { value: 'expiring', label: 'Expiring in 30 days' },
    { value: 'expired', label: 'Expired' },
];

const brandOptions = computed(() => [
    { value: '', label: 'All brands' },
    ...brands.value.map((name) => ({ value: name, label: name })),
]);

const activeFilterCount = computed(() =>
    [search.value, statusFilter.value, brandFilter.value, warrantyFilter.value, vehicleFilter.value].filter(Boolean).length,
);

const showFormModal = ref(false);
const showInstallModal = ref(false);
const showReplaceModal = ref(false);
const showRemoveModal = ref(false);
const showInspectionModal = ref(false);
const showScrapModal = ref(false);
const showDeleteConfirm = ref(false);

const selectedBattery = ref(null);
const batteryToDelete = ref(null);
const deleting = ref(false);

function isPillActive(pill) {
    return statusFilter.value === pill.status && warrantyFilter.value === pill.warranty;
}

function selectPill(pill) {
    statusFilter.value = pill.status;
    warrantyFilter.value = pill.warranty;
    pagination.value.currentPage = 1;
    fetchBatteries();
}

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function applyFilters() {
    pagination.value.currentPage = 1;
    fetchBatteries();
}

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
    brandFilter.value = '';
    warrantyFilter.value = '';
    vehicleFilter.value = '';
    pagination.value.currentPage = 1;
    fetchBatteries();
}

async function fetchCounts() {
    countsLoading.value = true;
    try {
        const res = await getBatteryCounts();
        counts.value = res.counts;
        brands.value = res.brands;
    } catch {
        toast.error('Could not load battery summary counts.');
    } finally {
        countsLoading.value = false;
    }
}

async function fetchBatteries() {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await listBatteries({
            page: pagination.value.currentPage,
            perPage: pagination.value.perPage,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            brand: brandFilter.value || undefined,
            vehicleId: vehicleFilter.value || undefined,
            warranty: warrantyFilter.value || undefined,
        });

        batteries.value = res.data;
        pagination.value = {
            currentPage: res.meta.current_page ?? 1,
            lastPage: res.meta.last_page ?? 1,
            perPage: res.meta.per_page ?? pagination.value.perPage,
            total: res.meta.total ?? 0,
            from: res.meta.from ?? 0,
            to: res.meta.to ?? 0,
        };
    } catch (err) {
        batteries.value = [];
        loadError.value = err.response?.data?.message || 'Failed to load batteries.';
    } finally {
        loading.value = false;
    }
}

function onPageChange(page) {
    pagination.value.currentPage = page;
    fetchBatteries();
}

function onPerPageChange(perPage) {
    pagination.value.perPage = perPage;
    pagination.value.currentPage = 1;
    fetchBatteries();
}

function goToDetail(id) {
    router.push({ name: 'batteries.show', params: { id } });
}

function openCreate() {
    selectedBattery.value = null;
    showFormModal.value = true;
}

function openEdit(b) {
    if (b.status === 'SCRAPPED') {
        return;
    }
    selectedBattery.value = b;
    showFormModal.value = true;
}

function openInstall(b) {
    selectedBattery.value = b;
    showInstallModal.value = true;
}

function openReplace(b) {
    selectedBattery.value = b;
    showReplaceModal.value = true;
}

function openRemove(b) {
    selectedBattery.value = b;
    showRemoveModal.value = true;
}

function openInspection(b) {
    selectedBattery.value = b;
    showInspectionModal.value = true;
}

function openScrap(b) {
    selectedBattery.value = b;
    showScrapModal.value = true;
}

function promptDelete(b) {
    if (b.status === 'SCRAPPED') {
        return;
    }
    batteryToDelete.value = b;
    showDeleteConfirm.value = true;
}

async function confirmDelete() {
    if (!batteryToDelete.value) {
        return;
    }
    deleting.value = true;
    try {
        await deleteBattery(batteryToDelete.value.id);
        toast.success(`Battery ${batteryToDelete.value.serial_number} deleted.`);
        showDeleteConfirm.value = false;
        batteryToDelete.value = null;
        await refreshAll();
    } catch (err) {
        toast.error(err.response?.data?.errors?.battery?.[0] || err.response?.data?.message || 'Failed to delete battery.');
    } finally {
        deleting.value = false;
    }
}

function onSaved() {
    toast.success(selectedBattery.value ? 'Battery updated.' : 'Battery added to inventory.');
    refreshAll();
}

function onActionDone(message) {
    toast.success(message);
    refreshAll();
}

async function refreshAll() {
    await Promise.all([fetchBatteries(), fetchCounts()]);
}

onMounted(async () => {
    if (route.query.create === '1') {
        openCreate();
    }

    listAllVehicles()
        .then((list) => { vehicles.value = list; })
        .catch(() => { vehicles.value = []; });

    await refreshAll();
});

watch(
    () => route.query.create,
    (val) => {
        if (val === '1') {
            openCreate();
        }
    },
);
</script>
