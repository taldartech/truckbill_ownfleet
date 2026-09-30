<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing batteries.
        </div>

        <!-- Inventory Status Badges / Quick Filter Strip -->
        <div class="row g-2 mb-3">
            <div
                v-for="pill in statusPills"
                :key="pill.key"
                class="col"
            >
                <div
                    class="card border h-100 cursor-pointer user-select-none transition-all py-2 px-3"
                    :class="[
                        statusFilter === pill.value ? 'bg-primary text-white shadow-sm' : 'bg-white text-dark',
                        'hover-shadow'
                    ]"
                    style="cursor: pointer;"
                    @click="selectStatusPill(pill.value)"
                >
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="small fw-semibold" :class="statusFilter === pill.value ? 'text-white' : 'text-muted'">
                            {{ pill.label }}
                        </div>
                        <i :class="[pill.icon, statusFilter === pill.value ? 'text-white-50' : 'text-muted']"></i>
                    </div>
                    <div class="fs-5 fw-bold mt-1" :class="statusFilter === pill.value ? 'text-white' : pill.textClass">
                        {{ Number(counts[pill.key] || 0).toLocaleString() }}
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
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canManage"
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
                    <label class="form-label">Status</label>
                    <AppSelect
                        v-model="statusFilter"
                        :options="statusOptions"
                        placeholder="All statuses"
                        :disabled="loading"
                    />
                </div>
                <div>
                    <label class="form-label">Brand</label>
                    <input
                        v-model="brandFilter"
                        type="text"
                        list="filter-brands"
                        class="form-control form-control-sm"
                        placeholder="All brands"
                        :disabled="loading"
                        @keydown.enter.prevent="applyFilters"
                    />
                    <datalist id="filter-brands">
                        <option value="Exide" />
                        <option value="Amaron" />
                        <option value="Tata Green" />
                        <option value="Luminous" />
                        <option value="SF Sonic" />
                        <option value="Livguard" />
                    </datalist>
                </div>
                <div>
                    <label class="form-label" for="batteryIndex_vehicle">Truck</label>
                    <VehicleSelect
                        input-id="batteryIndex_vehicle"
                        v-model="vehicleFilter"
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
                            <th>Brand / Model</th>
                            <th>Specs</th>
                            <th>Status</th>
                            <th>Truck / Slot</th>
                            <th>Running KM</th>
                            <th>Warranty</th>
                            <th>Purchase</th>
                            <th class="app-index-actions-cell" style="width: 170px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && batteries.length === 0" class="app-index-empty-row">
                            <td colspan="9" class="text-center text-muted py-4">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Loading batteries…
                            </td>
                        </tr>
                        <tr v-else-if="batteries.length === 0" class="app-index-empty-row">
                            <td colspan="9" class="text-center text-muted py-4">
                                No batteries found. Click "+ Add Battery" to add your first battery.
                            </td>
                        </tr>
                        <tr v-for="b in batteries" :key="b.id">
                            <td>
                                <RouterLink
                                    :to="{ name: 'batteries.show', params: { id: b.id } }"
                                    class="fw-semibold text-decoration-none text-dark"
                                >
                                    <i class="fas fa-car-battery text-secondary me-1"></i>
                                    {{ b.serial_number }}
                                </RouterLink>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ b.brand }}</div>
                                <div v-if="b.model" class="text-muted small">{{ b.model }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ b.capacity_ah }} Ah
                                </span>
                                <span class="badge bg-light text-secondary border ms-1">
                                    {{ b.voltage }}
                                </span>
                                <div class="text-muted small mt-1">
                                    {{ formatBatteryType(b.battery_type) }}
                                </div>
                            </td>
                            <td>
                                <span class="badge" :class="statusBadgeClass(b.status)">
                                    {{ b.status_label || formatStatus(b.status) }}
                                </span>
                            </td>
                            <td>
                                <template v-if="b.active_installation?.vehicle">
                                    <RouterLink
                                        :to="{ name: 'trucks.show', params: { id: b.active_installation.vehicle.id } }"
                                        class="fw-semibold text-decoration-none text-primary"
                                    >
                                        <i class="fas fa-truck me-1"></i>
                                        {{ b.active_installation.vehicle.registration_number }}
                                    </RouterLink>
                                    <div class="text-muted small">
                                        Slot: {{ b.active_installation.slot_name || 'Main' }}
                                    </div>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <template v-if="b.active_installation">
                                    <span class="fw-semibold">{{ Number(b.active_installation.running_km || 0).toLocaleString() }} KM</span>
                                    <div class="text-muted small">Current Stint</div>
                                </template>
                                <template v-else-if="b.total_km > 0">
                                    <span>{{ Number(b.total_km).toLocaleString() }} KM</span>
                                    <div class="text-muted small">Total Life</div>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <template v-if="b.warranty_months">
                                    <span class="badge" :class="isWarrantyActive(b) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                                        {{ isWarrantyActive(b) ? 'Active' : 'Expired' }}
                                    </span>
                                    <div class="text-muted small mt-1">
                                        {{ b.warranty_months }} Months
                                    </div>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    {{ b.purchase_cost ? '₹' + Number(b.purchase_cost).toLocaleString() : '—' }}
                                </div>
                                <div v-if="b.purchase_date" class="text-muted small">
                                    {{ formatDate(b.purchase_date) }}
                                </div>
                            </td>
                            <td class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="view" @click="goToDetail(b.id)" />
                                    <AppCrudActionButton v-if="canManage" action="edit" @click="openEdit(b)" />

                                    <!-- Quick Install if In Stock or Removed -->
                                    <button
                                        v-if="canManage && (b.status === 'IN_STOCK' || b.status === 'REMOVED')"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-success"
                                        title="Install on truck"
                                        @click="openInstall(b)"
                                    >
                                        <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                    </button>

                                    <!-- Quick Replace if Installed -->
                                    <button
                                        v-if="canManage && b.status === 'INSTALLED'"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-primary"
                                        title="Replace battery"
                                        @click="openReplace(b)"
                                    >
                                        <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                                    </button>

                                    <!-- Scrap if not Installed and not Scrapped -->
                                    <button
                                        v-if="canManage && b.status !== 'INSTALLED' && b.status !== 'SCRAPPED'"
                                        type="button"
                                        class="btn btn-icon btn-sm btn-outline-warning"
                                        title="Scrap battery"
                                        @click="openScrap(b)"
                                    >
                                        <i class="fas fa-recycle" aria-hidden="true"></i>
                                    </button>

                                    <!-- Delete if not installed -->
                                    <AppCrudActionButton
                                        v-if="canDelete && b.status !== 'INSTALLED'"
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

        <!-- Modals -->
        <BatteryFormModal
            :show="showFormModal"
            :battery="selectedBattery"
            @close="showFormModal = false"
            @saved="onSaved"
        />

        <BatteryInstallModal
            :show="showInstallModal"
            :battery="selectedBattery"
            @close="showInstallModal = false"
            @installed="onInstalled"
        />

        <BatteryReplaceModal
            :show="showReplaceModal"
            :battery="selectedBattery"
            :vehicle="selectedBattery?.active_installation?.vehicle"
            :current-installation="selectedBattery?.active_installation"
            @close="showReplaceModal = false"
            @replaced="onReplaced"
        />

        <BatteryScrapModal
            :show="showScrapModal"
            :battery="selectedBattery"
            @close="showScrapModal = false"
            @scrapped="onScrapped"
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
            This will permanently remove the battery master record.
        </AppConfirmModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import { listBatteries, getBatteryCounts, deleteBattery } from '../../api/batteries';
import { listAllVehicles } from '../../api/vehicles';

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
import BatteryScrapModal from '../../components/batteries/BatteryScrapModal.vue';

const router = useRouter();
const route = useRoute();
const { user, canInCompany, canCreateInCompany, canDeleteRecords } = usePermissions();

const needsCompanySelection = computed(() => user.value?.requires_company_selection === true);
const canManage = computed(() => canCreateInCompany('batteries'));
const canDelete = computed(() => canDeleteRecords.value && canInCompany('batteries.delete'));

const loading = ref(false);
const filtersOpen = ref(false);
const batteries = ref([]);
const vehicles = ref([]);

const search = ref('');
const statusFilter = ref('');
const brandFilter = ref('');
const vehicleFilter = ref('');

const counts = ref({
    total: 0,
    in_stock: 0,
    installed: 0,
    removed: 0,
    scrapped: 0,
});

const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    from: 0,
    to: 0,
});

const statusPills = [
    { key: 'total', value: '', label: 'All Batteries', icon: 'fas fa-layer-group', textClass: 'text-dark' },
    { key: 'in_stock', value: 'IN_STOCK', label: 'In Stock', icon: 'fas fa-boxes', textClass: 'text-info' },
    { key: 'installed', value: 'INSTALLED', label: 'Installed', icon: 'fas fa-truck', textClass: 'text-success' },
    { key: 'removed', value: 'REMOVED', label: 'Removed / Standby', icon: 'fas fa-pause-circle', textClass: 'text-warning' },
    { key: 'scrapped', value: 'SCRAPPED', label: 'Scrapped', icon: 'fas fa-recycle', textClass: 'text-secondary' },
];

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'IN_STOCK', label: 'In Stock' },
    { value: 'INSTALLED', label: 'Installed' },
    { value: 'REMOVED', label: 'Removed' },
    { value: 'SCRAPPED', label: 'Scrapped' },
];

// Modals state
const showFormModal = ref(false);
const showInstallModal = ref(false);
const showReplaceModal = ref(false);
const showScrapModal = ref(false);
const showDeleteConfirm = ref(false);

const selectedBattery = ref(null);
const batteryToDelete = ref(null);
const deleting = ref(false);

function selectStatusPill(val) {
    statusFilter.value = val;
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
    vehicleFilter.value = '';
    pagination.value.currentPage = 1;
    fetchBatteries();
}

async function fetchCounts() {
    try {
        counts.value = await getBatteryCounts();
    } catch {
        // silent fail on counts
    }
}

async function fetchBatteries() {
    loading.value = true;
    try {
        const res = await listBatteries({
            page: pagination.value.currentPage,
            perPage: pagination.value.perPage,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            brand: brandFilter.value || undefined,
            vehicleId: vehicleFilter.value || undefined,
        });

        batteries.value = res.data;
        if (res.meta) {
            pagination.value = {
                currentPage: res.meta.current_page ?? 1,
                lastPage: res.meta.last_page ?? 1,
                perPage: res.meta.per_page ?? 15,
                total: res.meta.total ?? 0,
                from: res.meta.from ?? 0,
                to: res.meta.to ?? 0,
            };
        }
    } catch (err) {
        console.error('Failed to load batteries', err);
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

function openScrap(b) {
    selectedBattery.value = b;
    showScrapModal.value = true;
}

function promptDelete(b) {
    batteryToDelete.value = b;
    showDeleteConfirm.value = true;
}

async function confirmDelete() {
    if (!batteryToDelete.value) return;
    deleting.value = true;
    try {
        await deleteBattery(batteryToDelete.value.id);
        showDeleteConfirm.value = false;
        batteryToDelete.value = null;
        await refreshAll();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to delete battery.');
    } finally {
        deleting.value = false;
    }
}

function onSaved() {
    refreshAll();
}

function onInstalled() {
    refreshAll();
}

function onReplaced() {
    refreshAll();
}

function onScrapped() {
    refreshAll();
}

async function refreshAll() {
    await Promise.all([fetchBatteries(), fetchCounts()]);
}

function formatStatus(status) {
    const map = {
        IN_STOCK: 'In Stock',
        INSTALLED: 'Installed',
        REMOVED: 'Removed',
        SCRAPPED: 'Scrapped',
    };
    return map[status] || status;
}

function statusBadgeClass(status) {
    const map = {
        IN_STOCK: 'bg-info text-dark',
        INSTALLED: 'bg-success',
        REMOVED: 'bg-warning text-dark',
        SCRAPPED: 'bg-secondary',
    };
    return map[status] || 'bg-light text-dark border';
}

function formatBatteryType(type) {
    const map = {
        LEAD_ACID: 'Lead-Acid',
        MAINTENANCE_FREE: 'Maintenance Free',
        AGM: 'AGM',
        GEL: 'Gel',
        LITHIUM_ION: 'Lithium-Ion',
    };
    return map[type] || type;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function isWarrantyActive(b) {
    if (!b.purchase_date || !b.warranty_months) return false;
    const purchase = new Date(b.purchase_date);
    const expires = new Date(purchase);
    expires.setMonth(expires.getMonth() + Number(b.warranty_months));
    return expires >= new Date();
}

onMounted(async () => {
    try {
        vehicles.value = await listAllVehicles();
    } catch {
        // silent
    }

    if (route.query.create === '1') {
        openCreate();
    }

    await Promise.all([fetchBatteries(), fetchCounts()]);
});

watch(
    () => route.query.create,
    (val) => {
        if (val === '1') {
            openCreate();
        }
    }
);
</script>

<style scoped>
.hover-shadow {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.hover-shadow:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}
</style>
