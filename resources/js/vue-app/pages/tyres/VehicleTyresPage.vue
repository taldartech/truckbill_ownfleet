<template>
    <div class="container mt-4 vehicle-tyres-page">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink :to="{ name: 'trucks.index' }" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Trucks
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">Tyre Positions</h3>
                    <span v-if="vehicleLabel" class="badge bg-secondary">{{ vehicleLabel }}</span>
                </div>
                <p v-if="vehicle" class="text-muted small mb-0">
                    {{ vehicle.vehicle_type_label ?? vehicle.vehicle_type }}
                    <span v-if="vehicle.make || vehicle.model"> · {{ formatMakeModel(vehicle) }}</span>
                </p>
            </div>
            <RouterLink
                v-if="vehicle"
                :to="{ name: 'trucks.show', params: { vehicleId: vehicle.id } }"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-truck"></i> Truck Details
            </RouterLink>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading truck tyre data…</div>
        <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-primary">
                        <div class="card-body py-3">
                            <div class="text-muted small">Tyres Used (Total)</div>
                            <div class="fs-4 fw-bold text-primary">{{ summary.unique_tyres_used ?? 0 }}</div>
                            <div class="small text-muted">unique tyres on this truck</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-success">
                        <div class="card-body py-3">
                            <div class="text-muted small">Currently Installed</div>
                            <div class="fs-4 fw-bold text-success">{{ summary.currently_installed ?? 0 }}</div>
                            <div class="small text-muted">
                                of {{ summary.total_positions ?? positions.length }} positions
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-warning">
                        <div class="card-body py-3">
                            <div class="text-muted small">Empty Positions</div>
                            <div class="fs-4 fw-bold text-warning">{{ summary.empty_positions ?? 0 }}</div>
                            <div class="small text-muted">need tyres</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-info">
                        <div class="card-body py-3">
                            <div class="text-muted small">Total KM on Truck</div>
                            <div class="fs-4 fw-bold text-info">{{ formatKm(summary.total_km_on_truck) }}</div>
                            <div class="small text-muted">{{ summary.total_stints ?? 0 }} install stints</div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === 'positions' }"
                        @click="activeTab = 'positions'"
                    >
                        <i class="bi bi-grid-3x3-gap me-1"></i> Current Positions
                    </button>
                </li>
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === 'history' }"
                        @click="activeTab = 'history'"
                    >
                        <i class="bi bi-clock-history me-1"></i> Tyre History
                        <span v-if="history.length" class="badge bg-secondary ms-1">{{ history.length }}</span>
                    </button>
                </li>
            </ul>

            <div v-if="activeTab === 'positions'" class="positions-layout">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>Truck Chassis View</strong>
                                <span class="text-muted small">
                                    <template v-if="canManage">Click free slot to install · use × to remove</template>
                                    <template v-else>Green = tyre on, dashed = free</template>
                                </span>
                            </div>
                            <div class="card-body">
                                <TruckTyreChassis
                                    :positions="positions"
                                    :interactive="canManage"
                                    :can-manage="canManage"
                                    @install="openInstallModal"
                                    @remove="openRemoveModal"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header py-2"><strong>Installed Tyres</strong></div>
                            <div class="card-body p-0">
                                <ul v-if="installedSlots.length" class="list-group list-group-flush small">
                                    <li
                                        v-for="slot in installedSlots"
                                        :key="slot.position"
                                        class="list-group-item"
                                    >
                                        <div class="fw-semibold">{{ slot.position_label }}</div>
                                        <RouterLink
                                            :to="{ name: 'tyres.show', params: { id: slot.tyre.id } }"
                                            class="text-decoration-none"
                                        >
                                            {{ slot.tyre.serial_number }}
                                        </RouterLink>
                                        <span class="text-muted"> · {{ slot.tyre.brand || '—' }}</span>
                                        <div class="text-muted mt-1">{{ formatKm(slot.tyre.stint_km) }} on truck</div>
                                        <div
                                            v-if="slot.tyre.remaining_life_km != null"
                                            class="progress mt-1"
                                            style="height: 5px"
                                        >
                                            <div
                                                class="progress-bar"
                                                :class="lifeBarClass(slot.tyre.life_used_percent)"
                                                :style="{
                                                    width: Math.min(100, slot.tyre.life_used_percent || 0) + '%',
                                                }"
                                            ></div>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="text-muted small mb-0 p-3">No tyres installed on this truck.</p>
                            </div>
                        </div>

                        <div v-if="spareSlots.length" class="card mt-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <strong>Spare Tyres</strong>
                                <span class="badge bg-info text-dark">{{ spareSlots.length }}</span>
                            </div>
                            <div class="card-body py-2 small">
                                <ul class="list-group list-group-flush">
                                    <li
                                        v-for="slot in spareSlots"
                                        :key="slot.position"
                                        class="list-group-item px-0 d-flex justify-content-between align-items-center"
                                    >
                                        <span>{{ slot.position_label }}</span>
                                        <span v-if="slot.tyre" class="badge bg-success">
                                            {{ slot.tyre.serial_number }}
                                        </span>
                                        <span v-else class="badge bg-light text-muted border">Free</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-if="emptyRoadSlots.length" class="card mt-3">
                            <div class="card-header py-2">
                                <strong>Free Road Positions</strong>
                                <span class="badge bg-warning text-dark ms-1">{{ emptyRoadSlots.length }}</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="d-flex flex-wrap gap-1">
                                    <span
                                        v-for="slot in emptyRoadSlots"
                                        :key="slot.position"
                                        class="badge bg-light text-muted border"
                                    >
                                        {{ slot.position_label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Truck Tyre History</strong>
                    <span class="text-muted small">
                        {{ summary.unique_tyres_used ?? 0 }} unique tyres · {{ summary.total_stints ?? 0 }} stints
                    </span>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tyre</th>
                                <th>Position</th>
                                <th>Installed</th>
                                <th>Removed</th>
                                <th class="text-end">KM on Truck</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="history.length === 0">
                                <td colspan="6" class="text-center text-muted py-4">
                                    No tyre history for this truck yet.
                                </td>
                            </tr>
                            <tr v-for="row in history" :key="row.id" :class="{ 'table-success': row.is_active }">
                                <td>
                                    <RouterLink
                                        v-if="row.tyre_id"
                                        :to="{ name: 'tyres.show', params: { id: row.tyre_id } }"
                                        class="fw-semibold text-decoration-none"
                                    >
                                        {{ row.tyre_serial_number }}
                                    </RouterLink>
                                    <div class="text-muted small">{{ row.tyre_brand || '—' }}</div>
                                </td>
                                <td>{{ row.position_label }}</td>
                                <td>{{ formatDate(row.installed_at) }}</td>
                                <td>
                                    <span v-if="row.is_active" class="badge bg-success">On truck</span>
                                    <span v-else>{{ formatDate(row.removed_at) }}</span>
                                </td>
                                <td class="text-end fw-semibold">{{ formatKm(row.stint_km) }}</td>
                                <td>
                                    <span class="badge" :class="tyreStatusBadgeClass(row.stint_status ?? row.tyre_status)">
                                        {{ formatTyreStatus(row.stint_status ?? row.tyre_status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <TyreActionModal
            v-if="showInstallModal"
            title="Install Tyre on Position"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeInstallModal"
            @submit="submitInstall"
        >
            <div class="alert alert-info small py-2">
                Position: <strong>{{ selectedSlot?.position_label }}</strong>
                · Truck: <strong>{{ vehicleLabel }}</strong>
            </div>
            <div class="mb-2">
                <label class="form-label">Tyre from stock</label>
                <AppSelect
                    v-model="installForm.tyre_id"
                    :options="stockTyreOptions"
                    placeholder="Select in-stock tyre"
                    search-placeholder="Search tyre…"
                />
            </div>
            <TyreMasterReferenceStrip
                v-if="selectedInstallTyre"
                class="mb-3"
                :tyre="selectedInstallTyre"
            />
            <div class="mb-2">
                <label class="form-label">Installation date</label>
                <AppDateInput v-model="installForm.installed_at" :max="maxTyreActionDate" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Current meter reading (km)</label>
                <input
                    v-model.number="installForm.km_at_installation"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    required
                />
                <div class="form-text">Uses the truck's latest odometer reading by default.</div>
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRemoveModal"
            title="Remove Tyre from Position"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeRemoveModal"
            @submit="submitRemove"
        >
            <div class="alert alert-secondary small py-2">
                {{ selectedSlot?.position_label }} ·
                <strong>{{ selectedSlot?.tyre?.serial_number }}</strong>
            </div>
            <div class="mb-2">
                <label class="form-label">Remove date</label>
                <AppDateInput v-model="removeForm.removed_at" :max="maxTyreActionDate" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Current meter reading (km)</label>
                <input
                    v-model.number="removeForm.odometer_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    required
                    @input="loadRemovePreview"
                />
            </div>
            <div v-if="calculatedRemoveStint != null" class="alert alert-secondary small py-2 mb-2">
                KM on truck: <strong>{{ calculatedRemoveStint }} km</strong>
            </div>
            <div v-if="removePreview" class="alert py-2 small" :class="removePreview.level === 'warning' ? 'alert-warning' : 'alert-info'">
                {{ removePreview.message }}
            </div>
        </TyreActionModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { fetchVehicle } from '../../api/vehicles';
import {
    getVehicleTyreHistory,
    getVehicleTyrePositions,
    installTyre,
    listTyres,
    previewTyreRemoval,
    removeTyreInstallation,
} from '../../api/tyres';
import TyreActionModal from '../../components/tyres/TyreActionModal.vue';
import TyreMasterReferenceStrip from '../../components/tyres/TyreMasterReferenceStrip.vue';
import TruckTyreChassis from '../../components/tyres/TruckTyreChassis.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { formatDate, toDateInputValue } from '../../utils/datetime';
import { calculateTyreStintKm } from '../../utils/tyreStint';

const route = useRoute();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));
const { formError, clearErrors, applyApiError } = useApiFormErrors();
const positions = ref([]);
const history = ref([]);
const summary = ref({});
const loading = ref(true);
const error = ref('');
const vehicleLabel = ref('');
const vehicle = ref(null);
const activeTab = ref('positions');
const stockTyres = ref([]);
const showInstallModal = ref(false);
const showRemoveModal = ref(false);
const selectedSlot = ref(null);
const actionSaving = ref(false);
const removePreview = ref(null);

const installForm = reactive({
    tyre_id: '',
    installed_at: toDateInputValue(),
    km_at_installation: 0,
});

const maxTyreActionDate = toDateInputValue();

const selectedInstallTyre = computed(() =>
    stockTyres.value.find((row) => String(row.id) === String(installForm.tyre_id)) ?? null,
);

const removeForm = reactive({
    removed_at: toDateInputValue(),
    odometer_km: 0,
});

const stockTyreOptions = computed(() =>
    stockTyres.value.map((tyre) => ({
        value: tyre.id,
        label: `${tyre.serial_number} — ${tyre.brand ?? '—'}`,
    })),
);

const calculatedRemoveStint = computed(() => {
    const odometer = removeForm.odometer_km;
    const kmAtInstall = selectedSlot.value?.tyre?.km_at_installation ?? 0;

    if (odometer === '' || odometer == null || Number.isNaN(Number(odometer))) {
        return null;
    }

    return calculateTyreStintKm(kmAtInstall, odometer);
});

const installedSlots = computed(() => positions.value.filter((slot) => !slot.is_empty && slot.tyre));
const spareSlots = computed(() => positions.value.filter((slot) => slot.is_spare));
const emptyRoadSlots = computed(() => positions.value.filter((slot) => slot.is_empty && !slot.is_spare));

function formatMakeModel(v) {
    return [v.make, v.model].filter(Boolean).join(' ');
}

function formatKm(value) {
    if (value == null || value === '') {
        return '—';
    }
    return `${Number(value).toLocaleString('en-IN')} km`;
}

function lifeBarClass(percent) {
    if (percent >= 85) {
        return 'bg-danger';
    }
    if (percent >= 60) {
        return 'bg-warning';
    }
    return 'bg-success';
}

function formatTyreStatus(status) {
    if (!status) {
        return '—';
    }
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function tyreStatusBadgeClass(status) {
    const map = {
        INSTALLED: 'bg-success',
        IN_STOCK: 'bg-secondary',
        SCRAPPED: 'bg-dark',
    };
    return map[status] ?? 'bg-secondary';
}

function buildSummaryFromPositions(positionSlots, historyRows = []) {
    const installed = positionSlots.filter((slot) => !slot.is_empty && slot.tyre);
    const uniqueFromHistory = new Set(historyRows.map((row) => row.tyre_id).filter(Boolean));
    const uniqueFromPositions = new Set(installed.map((slot) => slot.tyre.id));

    return {
        unique_tyres_used: uniqueFromHistory.size || uniqueFromPositions.size,
        total_stints: historyRows.length || installed.length,
        currently_installed: installed.length,
        empty_positions: positionSlots.filter((slot) => slot.is_empty).length,
        total_positions: positionSlots.length,
        total_km_on_truck: installed.reduce((sum, slot) => sum + (Number(slot.tyre?.stint_km) || 0), 0),
    };
}

async function loadStockTyres() {
    const res = await listTyres({ perPage: 100, status: 'IN_STOCK' });
    stockTyres.value = res.data;
}

async function reloadPositions() {
    const vehicleId = route.params.vehicleId;
    positions.value = await getVehicleTyrePositions(vehicleId);

    try {
        const historyData = await getVehicleTyreHistory(vehicleId);
        history.value = historyData.history;
        summary.value =
            historyData.history.length > 0 || historyData.summary?.total_stints > 0
                ? historyData.summary
                : buildSummaryFromPositions(positions.value, historyData.history);
    } catch {
        history.value = [];
        summary.value = buildSummaryFromPositions(positions.value);
    }
}

function prefillVehicleOdometer() {
    installForm.km_at_installation = Number(vehicle.value?.odometer_km ?? 0);
    removeForm.odometer_km = Number(vehicle.value?.odometer_km ?? 0);
}

async function openInstallModal(slot) {
    clearErrors();
    selectedSlot.value = slot;
    installForm.tyre_id = '';
    installForm.installed_at = toDateInputValue();
    prefillVehicleOdometer();
    await loadStockTyres();
    showInstallModal.value = true;
}

function closeInstallModal() {
    showInstallModal.value = false;
    selectedSlot.value = null;
    clearErrors();
}

function openRemoveModal(slot) {
    clearErrors();
    selectedSlot.value = slot;
    removeForm.removed_at = toDateInputValue();
    prefillVehicleOdometer();
    removePreview.value = null;
    showRemoveModal.value = true;
    loadRemovePreview();
}

function closeRemoveModal() {
    showRemoveModal.value = false;
    selectedSlot.value = null;
    removePreview.value = null;
    clearErrors();
}

async function loadRemovePreview() {
    const installationId = selectedSlot.value?.installation_id;
    if (!installationId || calculatedRemoveStint.value == null) {
        removePreview.value = null;
        return;
    }

    const res = await previewTyreRemoval(installationId, { odometerKm: removeForm.odometer_km });
    removePreview.value = res.data ?? null;
}

async function submitInstall() {
    clearErrors();
    actionSaving.value = true;

    try {
        await installTyre(installForm.tyre_id, {
            vehicle_id: route.params.vehicleId,
            position_code: selectedSlot.value?.position,
            installed_at: installForm.installed_at,
            km_at_installation: installForm.km_at_installation,
        });
        closeInstallModal();
        vehicle.value = await fetchVehicle(route.params.vehicleId);
        vehicleLabel.value = vehicle.value?.registration_number ?? vehicleLabel.value;
        await reloadPositions();
    } catch (e) {
        applyApiError(e, 'Unable to install tyre. Please check the form and try again.');
    } finally {
        actionSaving.value = false;
    }
}

async function submitRemove() {
    const installationId = selectedSlot.value?.installation_id;
    if (!installationId) {
        return;
    }

    clearErrors();
    actionSaving.value = true;

    try {
        await removeTyreInstallation(installationId, {
            removed_at: removeForm.removed_at,
            odometer_km: removeForm.odometer_km,
        });
        closeRemoveModal();
        vehicle.value = await fetchVehicle(route.params.vehicleId);
        vehicleLabel.value = vehicle.value?.registration_number ?? vehicleLabel.value;
        await reloadPositions();
    } catch (e) {
        applyApiError(e, 'Unable to remove tyre. Please check the form and try again.');
    } finally {
        actionSaving.value = false;
    }
}

onMounted(async () => {
    try {
        const vehicleId = route.params.vehicleId;
        const [vehicleData, positionData] = await Promise.all([
            fetchVehicle(vehicleId),
            getVehicleTyrePositions(vehicleId),
        ]);
        vehicle.value = vehicleData;
        vehicleLabel.value = vehicleData?.registration_number ?? vehicleId;
        positions.value = positionData;

        try {
            const historyData = await getVehicleTyreHistory(vehicleId);
            history.value = historyData.history;
            summary.value =
                historyData.history.length > 0 || historyData.summary?.total_stints > 0
                    ? historyData.summary
                    : buildSummaryFromPositions(positionData, historyData.history);
        } catch {
            history.value = [];
            summary.value = buildSummaryFromPositions(positionData);
        }
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Could not load tyre data for this truck.';
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.stat-card {
    transition: box-shadow 0.15s ease;
}

.stat-card:hover {
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
}
</style>
