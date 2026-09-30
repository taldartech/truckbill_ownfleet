<template>
    <div class="container mt-4 app-detail-page">
        <!-- Back Navigation & Top Actions Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <RouterLink
                    :to="{ name: 'batteries.index' }"
                    class="btn btn-outline-secondary btn-sm"
                    title="Back to Batteries"
                >
                    <i class="fas fa-arrow-left me-1"></i> Batteries
                </RouterLink>
                <div class="h4 mb-0 fw-bold d-flex align-items-center gap-2">
                    <i class="fas fa-car-battery text-warning"></i>
                    {{ battery?.serial_number || 'Battery Details' }}
                    <span v-if="battery?.status" class="badge" :class="statusBadgeClass(battery.status)">
                        {{ formatStatus(battery.status) }}
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button
                    v-if="canManage && battery"
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    @click="showEditModal = true"
                >
                    <i class="fas fa-pencil-alt me-1"></i> Edit
                </button>

                <button
                    v-if="canManage && battery && battery.status === 'INSTALLED'"
                    type="button"
                    class="btn btn-primary btn-sm"
                    @click="showReplaceModal = true"
                >
                    <i class="fas fa-arrows-rotate me-1"></i> Replace Battery
                </button>

                <button
                    v-if="canManage && battery && (battery.status === 'IN_STOCK' || battery.status === 'REMOVED')"
                    type="button"
                    class="btn btn-success btn-sm"
                    @click="showInstallModal = true"
                >
                    <i class="fas fa-plus-circle me-1"></i> Install on Truck
                </button>

                <button
                    v-if="canManage && battery && battery.status !== 'INSTALLED' && battery.status !== 'SCRAPPED'"
                    type="button"
                    class="btn btn-outline-danger btn-sm"
                    @click="showScrapModal = true"
                >
                    <i class="fas fa-recycle me-1"></i> Scrap
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Loading battery details…
        </div>

        <template v-else-if="battery">
            <!-- Lifecycle KPI Cards -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card p-3 border rounded shadow-sm h-100">
                        <div class="text-muted small text-uppercase fw-semibold">Total Running Distance</div>
                        <div class="fs-4 fw-bold text-primary mt-1">
                            {{ Number(lifeSummary?.total_running_km || battery.total_km || 0).toLocaleString() }} KM
                        </div>
                        <div class="text-muted small mt-1">
                            Across {{ lifeSummary?.installations_count || battery.installations?.length || 0 }} stints
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card p-3 border rounded shadow-sm h-100">
                        <div class="text-muted small text-uppercase fw-semibold">Warranty Status</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge" :class="lifeSummary?.is_in_warranty ? 'bg-success' : 'bg-secondary'">
                                {{ lifeSummary?.is_in_warranty ? 'Under Warranty' : 'Expired' }}
                            </span>
                            <span v-if="lifeSummary?.warranty_remaining_days > 0" class="small fw-semibold text-success">
                                {{ lifeSummary.warranty_remaining_days }} days left
                            </span>
                        </div>
                        <div class="text-muted small mt-1">
                            {{ battery.warranty_months ? `${battery.warranty_months} months cover` : 'No warranty' }}
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card p-3 border rounded shadow-sm h-100">
                        <div class="text-muted small text-uppercase fw-semibold">Battery Age</div>
                        <div class="fs-4 fw-bold text-dark mt-1">
                            {{ lifeSummary?.age_months ?? calculateAgeMonths(battery.purchase_date) }} Months
                        </div>
                        <div class="text-muted small mt-1">
                            Purchased: {{ formatDate(battery.purchase_date) }}
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card p-3 border rounded shadow-sm h-100">
                        <div class="text-muted small text-uppercase fw-semibold">Purchase Value</div>
                        <div class="fs-4 fw-bold text-dark mt-1">
                            {{ battery.purchase_cost ? '₹' + Number(battery.purchase_cost).toLocaleString() : '—' }}
                        </div>
                        <div class="text-muted small mt-1">
                            Supplier: {{ battery.supplier_name || '—' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Left Column: Specs & Installation History -->
                <div class="col-lg-8">
                    <!-- Specifications Card -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <strong>Technical Specifications</strong>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 small">
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Serial Number</span>
                                    <div class="fw-bold fs-6">{{ battery.serial_number }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Brand</span>
                                    <div class="fw-semibold">{{ battery.brand }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Model</span>
                                    <div>{{ battery.model || '—' }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Battery Chemistry / Type</span>
                                    <div>{{ formatBatteryType(battery.battery_type) }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Capacity (Ah)</span>
                                    <div class="fw-semibold">{{ battery.capacity_ah }} Ah</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Voltage</span>
                                    <div class="fw-semibold">{{ battery.voltage }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Warranty Period</span>
                                    <div>{{ battery.warranty_months ? `${battery.warranty_months} Months` : '—' }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Warranty Valid Until</span>
                                    <div>{{ formatDate(battery.warranty_valid_until) }}</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <span class="text-muted">Expected Life</span>
                                    <div>{{ battery.expected_life_months ? `${battery.expected_life_months} Months` : '—' }}</div>
                                </div>
                                <div v-if="battery.notes" class="col-12">
                                    <span class="text-muted">Notes</span>
                                    <div class="p-2 bg-light rounded border text-muted mt-1">{{ battery.notes }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scrap Details if Scrapped -->
                    <div v-if="battery.status === 'SCRAPPED'" class="card border-danger-subtle mb-3">
                        <div class="card-header bg-danger-subtle text-danger fw-bold">
                            <i class="fas fa-recycle me-1"></i> Scrapped Battery Record
                        </div>
                        <div class="card-body small">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <span class="text-muted">Scrapped Date</span>
                                    <div class="fw-bold">{{ formatDate(battery.scrapped_at) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Scrap Recovery Amount</span>
                                    <div class="fw-bold text-success">
                                        {{ battery.scrap_sale_amount ? '₹' + Number(battery.scrap_sale_amount).toLocaleString() : '₹0' }}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Notes</span>
                                    <div>{{ battery.scrap_notes || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Installation / Stint History -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Installation & Stint History</strong>
                            <span class="badge bg-light text-dark border">
                                {{ battery.installations?.length || 0 }} Stints
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="!battery.installations || battery.installations.length === 0" class="text-muted text-center py-4">
                                No installations recorded for this battery yet.
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Truck</th>
                                            <th>Slot</th>
                                            <th>Installed</th>
                                            <th>Removed</th>
                                            <th class="text-end">Install Odo</th>
                                            <th class="text-end">Removal Odo</th>
                                            <th class="text-end">Stint KM</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="inst in battery.installations" :key="inst.id">
                                            <td>
                                                <RouterLink
                                                    v-if="inst.vehicle_id"
                                                    :to="{ name: 'trucks.show', params: { id: inst.vehicle_id } }"
                                                    class="fw-semibold text-decoration-none text-primary"
                                                >
                                                    <i class="fas fa-truck me-1"></i>
                                                    {{ inst.vehicle_registration || 'Truck #' + inst.vehicle_id }}
                                                </RouterLink>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    {{ inst.slot_name || 'Main' }}
                                                </span>
                                            </td>
                                            <td>{{ formatDate(inst.installed_at) }}</td>
                                            <td>
                                                <span v-if="inst.removed_at">{{ formatDate(inst.removed_at) }}</span>
                                                <span v-else class="badge bg-success">Active Stint</span>
                                            </td>
                                            <td class="text-end">{{ Number(inst.km_at_installation || 0).toLocaleString() }}</td>
                                            <td class="text-end">
                                                {{ inst.km_at_removal ? Number(inst.km_at_removal).toLocaleString() : '—' }}
                                            </td>
                                            <td class="text-end fw-semibold text-primary">
                                                {{ Number(inst.running_km || (inst.km_at_removal ? inst.km_at_removal - inst.km_at_installation : 0)).toLocaleString() }} KM
                                            </td>
                                            <td>
                                                <span v-if="inst.removal_reason" class="badge bg-light text-secondary border">
                                                    {{ formatReason(inst.removal_reason) }}
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Active Assignment & Actions -->
                <div class="col-lg-4">
                    <!-- Current Active Vehicle Card -->
                    <div class="card mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <strong>Current Assignment</strong>
                            <span
                                class="badge"
                                :class="battery.active_installation ? 'bg-success' : 'bg-secondary'"
                            >
                                {{ battery.active_installation ? 'Assigned' : 'Unassigned' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <template v-if="battery.active_installation">
                                <div class="text-muted small text-uppercase fw-semibold">Mounted Truck</div>
                                <div class="fs-5 fw-bold text-dark mt-1 d-flex align-items-center gap-2">
                                    <i class="fas fa-truck text-primary"></i>
                                    <RouterLink
                                        :to="{ name: 'trucks.show', params: { id: battery.active_installation.vehicle_id } }"
                                        class="text-decoration-none text-dark"
                                    >
                                        {{ battery.active_installation.vehicle_registration }}
                                    </RouterLink>
                                </div>

                                <div class="mt-3 small border-top pt-2">
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-muted">Slot / Position:</span>
                                        <span class="fw-semibold">{{ battery.active_installation.slot_name || 'Main #1' }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-muted">Installed On:</span>
                                        <span>{{ formatDate(battery.active_installation.installed_at) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-muted">Install Meter:</span>
                                        <span>{{ Number(battery.active_installation.km_at_installation || 0).toLocaleString() }} KM</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-muted">Current Stint KM:</span>
                                        <span class="fw-bold text-primary">
                                            {{ Number(battery.active_installation.stint_km || 0).toLocaleString() }} KM
                                        </span>
                                    </div>
                                </div>

                                <div v-if="canManage" class="d-grid gap-2 mt-3 pt-2 border-top">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        @click="showReplaceModal = true"
                                    >
                                        <i class="fas fa-arrows-rotate me-1"></i> Replace Battery
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        @click="showRemoveConfirm = true"
                                    >
                                        <i class="fas fa-minus-circle me-1"></i> Remove from Truck
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-boxes fs-3 mb-2 text-secondary"></i>
                                    <div>This battery is currently in stock.</div>
                                    <button
                                        v-if="canManage && battery.status !== 'SCRAPPED'"
                                        type="button"
                                        class="btn btn-outline-success btn-sm mt-3"
                                        @click="showInstallModal = true"
                                    >
                                        <i class="fas fa-plus-circle me-1"></i> Install on Truck
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Record Meta Card -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <strong>Record Details</strong>
                        </div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(battery.created_at) }}</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(battery.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Modals -->
        <BatteryFormModal
            :show="showEditModal"
            :battery="battery"
            @close="showEditModal = false"
            @saved="onSaved"
        />

        <BatteryInstallModal
            :show="showInstallModal"
            :battery="battery"
            @close="showInstallModal = false"
            @installed="onInstalled"
        />

        <BatteryReplaceModal
            :show="showReplaceModal"
            :battery="battery"
            :vehicle="battery?.active_installation ? { id: battery.active_installation.vehicle_id, registration_number: battery.active_installation.vehicle_registration, odometer_km: battery.active_installation.vehicle_odometer_km } : null"
            :current-installation="battery?.active_installation"
            @close="showReplaceModal = false"
            @replaced="onReplaced"
        />

        <BatteryScrapModal
            :show="showScrapModal"
            :battery="battery"
            @close="showScrapModal = false"
            @scrapped="onScrapped"
        />

        <!-- Confirm Remove from Truck Modal -->
        <AppConfirmModal
            :show="showRemoveConfirm"
            title="Remove Battery from Truck"
            :loading="removing"
            confirm-button-text="Confirm Removal"
            @close="showRemoveConfirm = false"
            @confirm="confirmRemove"
        >
            Are you sure you want to remove battery
            <strong>{{ battery?.serial_number }}</strong>
            from truck
            <strong>{{ battery?.active_installation?.vehicle_registration }}</strong>?
            The battery status will be changed to <em>Removed / Standby</em>.
        </AppConfirmModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import { getBattery, removeBatteryInstallation } from '../../api/batteries';

import BatteryFormModal from '../../components/batteries/BatteryFormModal.vue';
import BatteryInstallModal from '../../components/batteries/BatteryInstallModal.vue';
import BatteryReplaceModal from '../../components/batteries/BatteryReplaceModal.vue';
import BatteryScrapModal from '../../components/batteries/BatteryScrapModal.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';

const route = useRoute();
const { canCreateInCompany } = usePermissions();

const canManage = computed(() => canCreateInCompany('batteries'));

const loading = ref(false);
const battery = ref(null);
const lifeSummary = ref(null);

const showEditModal = ref(false);
const showInstallModal = ref(false);
const showReplaceModal = ref(false);
const showScrapModal = ref(false);
const showRemoveConfirm = ref(false);
const removing = ref(false);

async function fetchBattery() {
    loading.value = true;
    try {
        const res = await getBattery(route.params.id);
        battery.value = res.battery;
        lifeSummary.value = res.life_summary;
    } catch (err) {
        console.error('Failed to load battery detail', err);
    } finally {
        loading.value = false;
    }
}

async function confirmRemove() {
    if (!battery.value?.active_installation?.id) return;
    removing.value = true;
    try {
        await removeBatteryInstallation(battery.value.active_installation.id, {
            removed_at: new Date().toISOString().slice(0, 10),
            removal_reason: 'NORMAL_ROTATION',
        });
        showRemoveConfirm.value = false;
        await fetchBattery();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to remove battery.');
    } finally {
        removing.value = false;
    }
}

function onSaved() {
    fetchBattery();
}

function onInstalled() {
    fetchBattery();
}

function onReplaced() {
    fetchBattery();
}

function onScrapped() {
    fetchBattery();
}

function formatStatus(status) {
    const map = {
        IN_STOCK: 'In Stock',
        INSTALLED: 'Installed',
        REMOVED: 'Removed / Standby',
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

function formatReason(reason) {
    const map = {
        NORMAL_ROTATION: 'Normal Rotation',
        FAILED: 'Battery Failed',
        MAINTENANCE: 'Maintenance / Servicing',
        SOLD: 'Vehicle Sold',
        SCRAPPED: 'Scrapped',
        OTHER: 'Other',
    };
    return map[reason] || reason;
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

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
}

function calculateAgeMonths(purchaseDate) {
    if (!purchaseDate) return 0;
    const p = new Date(purchaseDate);
    const now = new Date();
    return Math.max(0, (now.getFullYear() - p.getFullYear()) * 12 + (now.getMonth() - p.getMonth()));
}

onMounted(() => {
    fetchBattery();
});
</script>
