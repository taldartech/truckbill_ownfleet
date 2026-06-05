<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <RouterLink :to="{ name: 'tyres.index' }" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Tyres
                </RouterLink>
                <h3 class="mb-0 mt-1">{{ tyre?.serial_number ?? 'Tyre Detail' }}</h3>
            </div>
            <div v-if="canManage && tyre" class="d-flex flex-wrap gap-2">
                <button
                    v-if="!tyre.active_installation && tyre.status !== 'SCRAPPED'"
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    @click="showInstall = true"
                >
                    Install
                </button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-warning btn-sm"
                    @click="showRotate = true"
                >
                    Rotate
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="showRetread = true">Retread</button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-success btn-sm"
                    @click="showReplace = true"
                >
                    Replace
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" @click="showDamage = true">Damage</button>
                <button
                    v-if="tyre.status !== 'SCRAPPED' && !tyre.active_installation"
                    type="button"
                    class="btn btn-outline-dark btn-sm"
                    @click="handleScrap"
                >
                    Scrap
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading…</div>

        <template v-else-if="tyre">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><strong>Tyre Master</strong></div>
                        <div class="card-body row g-2 small">
                            <div class="col-md-4"><span class="text-muted">Brand</span><br />{{ tyre.brand || '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Size</span><br />{{ tyre.size || '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Type</span><br />{{ tyre.tyre_type || '—' }}</div>
                            <div class="col-md-4"><span class="text-muted">Status</span><br />{{ tyre.status }}</div>
                            <div class="col-md-4">
                                <span class="text-muted">Purchase</span><br />{{ tyre.purchase_date || '—' }} /
                                ₹{{ tyre.purchase_cost }}
                            </div>
                            <div class="col-md-4"><span class="text-muted">Total KM used</span><br />{{ lifeSummary?.total_km_used ?? tyre.current_km }}</div>
                            <div class="col-md-4">
                                <span class="text-muted">Expected life</span><br />{{ tyre.expected_life_km ?? 40000 }} km
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Life remaining</span><br />
                                <strong :class="lifeSummary?.is_early_wear_risk ? 'text-warning' : ''">
                                    {{ lifeSummary?.remaining_life_km ?? '—' }} km
                                </strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Spare used (times)</span><br />{{ lifeSummary?.spare_usage_count ?? 0 }}
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Repair cost total</span><br />₹{{ lifeSummary?.total_repair_cost ?? 0 }}
                            </div>
                            <div class="col-12" v-if="lifeSummary">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Life used {{ lifeSummary.life_used_percent }}%</span>
                                    <span>{{ lifeSummary.total_km_used }} / {{ lifeSummary.expected_life_km }} km</span>
                                </div>
                                <div class="progress" style="height: 10px">
                                    <div
                                        class="progress-bar"
                                        :class="lifeBarClass(lifeSummary.life_used_percent)"
                                        :style="{ width: Math.min(100, lifeSummary.life_used_percent) + '%' }"
                                    ></div>
                                </div>
                                <div v-if="lifeSummary.is_early_wear_risk" class="alert alert-warning py-2 mt-2 mb-0 small">
                                    Tyre has most of its life left — avoid early scrap; check tread before removing.
                                </div>
                            </div>
                            <div class="col-12" v-if="tyre.warranty_details">
                                <span class="text-muted">Warranty</span><br />{{ tyre.warranty_details }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><strong>Current Position</strong></div>
                        <div class="card-body">
                            <template v-if="tyre.active_installation">
                                <div>{{ tyre.active_installation.vehicle_registration }}</div>
                                <div class="text-muted">{{ tyre.active_installation.position_label }}</div>
                                <div class="small">
                                    Since {{ tyre.active_installation.installed_at }} @
                                    {{ tyre.active_installation.km_at_installation ?? '—' }} km
                                </div>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="btn btn-sm btn-warning mt-2"
                                    @click="handleRemove"
                                >
                                    Remove from vehicle
                                </button>
                            </template>
                            <span v-else class="text-muted">Not installed</span>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === 'timeline' }"
                        @click="activeTab = 'timeline'"
                    >
                        Full History (Timeline)
                    </button>
                </li>
                <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                    <button
                        type="button"
                        class="nav-link"
                        :class="{ active: activeTab === tab.key }"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </li>
            </ul>

            <div class="card">
                <div v-if="activeTab === 'timeline'" class="card-body">
                    <ul v-if="timeline.length" class="list-group list-group-flush">
                        <li v-for="(event, i) in timeline" :key="i" class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <strong>{{ event.title }}</strong>
                                <span class="text-muted small">{{ event.date }}</span>
                            </div>
                            <div class="small text-muted">{{ event.detail }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-muted mb-0">No history yet.</p>
                </div>
                <div v-else class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead v-if="activeColumns.length">
                            <tr>
                                <th v-for="col in activeColumns" :key="col">{{ col }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="activeRows.length === 0">
                                <td class="text-muted text-center">No records.</td>
                            </tr>
                            <tr v-for="(row, i) in activeRows" :key="i">
                                <td v-for="(cell, j) in row" :key="j">{{ cell }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <TyreActionModal
            v-if="showInstall"
            title="Install Tyre"
            @close="showInstall = false"
            @submit="submitInstall"
        >
            <div class="mb-2">
                <label class="form-label">Vehicle</label>
                <select v-model="actionForm.vehicle_id" class="form-select" required>
                    <option value="">Select</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.registration_number }}</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Position</label>
                <select
                    v-model="actionForm.position_code"
                    class="form-select"
                    required
                    :disabled="!actionForm.vehicle_id || loadingVehiclePositions"
                >
                    <option value="">
                        {{ loadingVehiclePositions ? 'Loading positions…' : 'Select truck first' }}
                    </option>
                    <option
                        v-for="p in installPositionOptions"
                        :key="p.value"
                        :value="p.value"
                        :disabled="p.occupied"
                    >
                        {{ p.label }}{{ p.occupied ? ' (occupied)' : '' }}
                    </option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Installation Date</label>
                <input v-model="actionForm.installed_at" type="date" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Truck odometer (KM) at install *</label>
                <input v-model.number="actionForm.km_at_installation" type="number" min="0" class="form-control" required />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showReplace"
            title="Replace Tyre on Truck"
            @close="showReplace = false"
            @submit="submitReplace"
        >
            <div class="alert alert-info small py-2">
                Position: <strong>{{ tyre?.active_installation?.position_label }}</strong> · Truck:
                <strong>{{ tyre?.active_installation?.vehicle_registration }}</strong>
            </div>
            <div class="mb-2">
                <label class="form-label">New tyre (from stock)</label>
                <select v-model="actionForm.new_tyre_id" class="form-select">
                    <option value="">Remove only (no new tyre)</option>
                    <option v-for="t in stockTyres" :key="t.id" :value="t.id">{{ t.serial_number }} — {{ t.brand }}</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Replace date</label>
                <input v-model="actionForm.replaced_at" type="date" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Truck KM at replace</label>
                <input
                    v-model.number="actionForm.km_at_replacement"
                    type="number"
                    min="0"
                    class="form-control"
                    @input="loadReplacePreview"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">Reason</label>
                <input v-model="actionForm.replace_reason" type="text" class="form-control" placeholder="Puncture, burst, rotation…" />
            </div>
            <div v-if="replacePreview" class="alert py-2 small" :class="replacePreview.level === 'warning' ? 'alert-warning' : 'alert-info'">
                {{ replacePreview.message }}
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRotate"
            title="Rotate Tyre"
            @close="showRotate = false"
            @submit="submitRotate"
        >
            <div class="mb-2">
                <label class="form-label">Previous Position</label>
                <input :value="tyre?.active_installation?.position" class="form-control" disabled />
            </div>
            <div class="mb-2">
                <label class="form-label">New Position</label>
                <select
                    v-model="actionForm.new_position"
                    class="form-select"
                    required
                    :disabled="loadingVehiclePositions"
                >
                    <option v-for="p in rotatePositionOptions" :key="p.value" :value="p.value">
                        {{ p.label }}{{ p.occupied ? ' (occupied)' : '' }}
                    </option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Rotation Date</label>
                <input v-model="actionForm.rotation_date" type="date" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Reason</label>
                <input v-model="actionForm.reason" type="text" class="form-control" />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRetread"
            title="Record Retread"
            @close="showRetread = false"
            @submit="submitRetread"
        >
            <div class="mb-2">
                <label class="form-label">Vendor</label>
                <input v-model="actionForm.vendor" type="text" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Cost</label>
                <input v-model.number="actionForm.cost" type="number" min="0" step="0.01" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Date</label>
                <input v-model="actionForm.retread_date" type="date" class="form-control" required />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showDamage"
            title="Record Damage"
            @close="showDamage = false"
            @submit="submitDamage"
        >
            <div class="mb-2">
                <label class="form-label">Type</label>
                <select v-model="actionForm.damage_type" class="form-select" required>
                    <option value="PUNCTURE">Puncture</option>
                    <option value="BURST">Burst</option>
                    <option value="CUT">Cut</option>
                    <option value="SIDEWALL">Sidewall</option>
                    <option value="OTHER">Other</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Position on truck</label>
                <input :value="tyre?.active_installation?.position_label || '—'" class="form-control" disabled />
            </div>
            <div class="mb-2">
                <label class="form-label">Date</label>
                <input v-model="actionForm.occurred_at" type="date" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">KM when damage happened</label>
                <input v-model.number="actionForm.km_at_occurrence" type="number" min="0" class="form-control" />
            </div>
            <div class="mb-2">
                <label class="form-label">Description</label>
                <input v-model="actionForm.damage_description" type="text" class="form-control" placeholder="Puncture, burst…" />
            </div>
            <div class="mb-2">
                <label class="form-label">Repair Cost</label>
                <input v-model.number="actionForm.repair_cost" type="number" min="0" step="0.01" class="form-control" />
            </div>
        </TyreActionModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import {
    getTyre,
    getVehicleTyrePositions,
    installTyre,
    listTyres,
    previewTyreRemoval,
    recordTyreDamage,
    removeTyreInstallation,
    replaceTyre,
    retreadTyre,
    rotateTyre,
    scrapTyre,
} from '../../api/tyres';
import { usePermissions } from '../../composables/usePermissions';
import TyreActionModal from '../../components/tyres/TyreActionModal.vue';

const route = useRoute();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));

const tyre = ref(null);
const lifeSummary = ref(null);
const timeline = ref([]);
const loading = ref(true);
const activeTab = ref('timeline');
const vehicles = ref([]);
const stockTyres = ref([]);
const showInstall = ref(false);
const showRotate = ref(false);
const showRetread = ref(false);
const showDamage = ref(false);
const showReplace = ref(false);
const replacePreview = ref(null);

const vehiclePositionSlots = ref([]);
const loadingVehiclePositions = ref(false);

const installPositionOptions = computed(() =>
    vehiclePositionSlots.value.map((slot) => ({
        value: slot.position,
        label: slot.position_label,
        occupied: !slot.is_empty,
    })),
);

const rotatePositionOptions = computed(() =>
    vehiclePositionSlots.value
        .filter((slot) => slot.position !== tyre.value?.active_installation?.position)
        .map((slot) => ({
            value: slot.position,
            label: slot.position_label,
            occupied: !slot.is_empty,
        })),
);

const actionForm = reactive({
    vehicle_id: '',
    position_code: '',
    installed_at: new Date().toISOString().slice(0, 10),
    new_position: 'FRONT_RIGHT',
    rotation_date: new Date().toISOString().slice(0, 10),
    reason: '',
    vendor: '',
    cost: 0,
    retread_date: new Date().toISOString().slice(0, 10),
    damage_type: 'PUNCTURE',
    occurred_at: new Date().toISOString().slice(0, 10),
    repair_cost: null,
    damage_description: '',
    km_at_occurrence: null,
    km_at_installation: null,
    new_tyre_id: '',
    replaced_at: new Date().toISOString().slice(0, 10),
    km_at_replacement: null,
    replace_reason: '',
});

const tabs = [
    { key: 'installations', label: 'Installation History' },
    { key: 'rotations', label: 'Rotations' },
    { key: 'retreads', label: 'Retreads' },
    { key: 'damages', label: 'Damage' },
    { key: 'expenses', label: 'Expenses' },
];

const activeRows = computed(() => {
    if (!tyre.value) {
        return [];
    }
    if (activeTab.value === 'installations') {
        return (tyre.value.installations ?? []).map((r) => [
            r.vehicle_registration,
            r.position_label,
            `${r.installed_at} @ ${r.km_at_installation} km`,
            r.removed_at ?? '—',
            r.running_km ?? '—',
            r.removal_recommendation ?? '—',
        ]);
    }
    if (activeTab.value === 'rotations') {
        return (tyre.value.rotations ?? []).map((r) => [
            r.vehicle_registration,
            r.rotation_date,
            r.previous_position,
            r.new_position,
            r.reason ?? '—',
        ]);
    }
    if (activeTab.value === 'retreads') {
        return (tyre.value.retreads ?? []).map((r) => [r.retread_date, r.vendor, r.cost, r.condition_after ?? '—']);
    }
    if (activeTab.value === 'damages') {
        return (tyre.value.damages ?? []).map((r) => [
            r.damage_type,
            r.vehicle_registration ?? '—',
            r.position_label ?? '—',
            r.km_at_occurrence ?? '—',
            r.repair_cost ?? '—',
            r.is_repaired ? 'Yes' : 'No',
        ]);
    }
    return (tyre.value.expenses ?? []).map((r) => [r.expense_type, r.expense_date, r.amount, r.notes ?? '—']);
});

const activeColumns = computed(() => {
    const map = {
        installations: ['Vehicle', 'Position', 'Installed @ KM', 'Removed', 'Ran KM', 'Advice'],
        rotations: ['Vehicle', 'Date', 'From', 'To', 'Reason'],
        retreads: ['Date', 'Vendor', 'Cost', 'Condition'],
        damages: ['Type', 'Truck', 'Position', 'KM', 'Repair', 'Fixed'],
        expenses: ['Type', 'Date', 'Amount', 'Notes'],
    };
    return map[activeTab.value] ?? [];
});

function lifeBarClass(percent) {
    if (percent >= 85) {
        return 'bg-danger';
    }
    if (percent >= 60) {
        return 'bg-warning';
    }
    return 'bg-success';
}

async function loadTyre() {
    loading.value = true;
    try {
        const payload = await getTyre(route.params.id);
        tyre.value = payload.tyre;
        lifeSummary.value = payload.life_summary;
        timeline.value = payload.timeline;
        if (tyre.value?.active_installation?.vehicle_id) {
            await loadVehiclePositions(tyre.value.active_installation.vehicle_id);
            const firstOpen = rotatePositionOptions.value.find((p) => !p.occupied);
            actionForm.new_position = firstOpen?.value ?? tyre.value?.active_installation?.position ?? '';
        }
        actionForm.km_at_replacement = null;
    } finally {
        loading.value = false;
    }
}

async function loadReplacePreview() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId) {
        return;
    }
    const res = await previewTyreRemoval(instId, actionForm.km_at_replacement);
    replacePreview.value = res.data ?? null;
}

async function loadVehiclePositions(vehicleId) {
    if (!vehicleId) {
        vehiclePositionSlots.value = [];
        actionForm.position_code = '';
        return;
    }

    loadingVehiclePositions.value = true;
    try {
        vehiclePositionSlots.value = await getVehicleTyrePositions(vehicleId);
        const firstEmpty = vehiclePositionSlots.value.find((slot) => slot.is_empty);
        if (firstEmpty && !actionForm.position_code) {
            actionForm.position_code = firstEmpty.position;
        }
    } finally {
        loadingVehiclePositions.value = false;
    }
}

watch(
    () => actionForm.vehicle_id,
    (vehicleId) => {
        actionForm.position_code = '';
        loadVehiclePositions(vehicleId);
    },
);

watch(showRotate, async (open) => {
    if (open && tyre.value?.active_installation?.vehicle_id) {
        await loadVehiclePositions(tyre.value.active_installation.vehicle_id);
        const firstOpen = rotatePositionOptions.value.find((p) => !p.occupied);
        if (firstOpen) {
            actionForm.new_position = firstOpen.value;
        }
    }
});

async function loadStockTyres() {
    const res = await listTyres({ perPage: 100, status: 'IN_STOCK' });
    stockTyres.value = res.data.filter((t) => t.id !== tyre.value?.id);
}

async function submitInstall() {
    await installTyre(tyre.value.id, {
        vehicle_id: actionForm.vehicle_id,
        position_code: actionForm.position_code,
        installed_at: actionForm.installed_at,
        km_at_installation: actionForm.km_at_installation,
    });
    showInstall.value = false;
    await loadTyre();
}

async function submitRotate() {
    await rotateTyre(tyre.value.id, {
        rotation_date: actionForm.rotation_date,
        previous_position: tyre.value.active_installation.position,
        new_position: actionForm.new_position,
        reason: actionForm.reason || null,
    });
    showRotate.value = false;
    await loadTyre();
}

async function submitRetread() {
    await retreadTyre(tyre.value.id, {
        vendor: actionForm.vendor,
        cost: actionForm.cost,
        retread_date: actionForm.retread_date,
    });
    showRetread.value = false;
    await loadTyre();
}

async function submitDamage() {
    await recordTyreDamage(tyre.value.id, {
        damage_type: actionForm.damage_type,
        occurred_at: actionForm.occurred_at,
        repair_cost: actionForm.repair_cost || null,
        description: actionForm.damage_description || null,
        km_at_occurrence: actionForm.km_at_occurrence || null,
    });
    showDamage.value = false;
    await loadTyre();
}

async function submitReplace() {
    await replaceTyre({
        vehicle_id: tyre.value.active_installation.vehicle_id,
        position_code: tyre.value.active_installation.position,
        old_tyre_id: tyre.value.id,
        new_tyre_id: actionForm.new_tyre_id || null,
        replaced_at: actionForm.replaced_at,
        km_at_replacement: actionForm.km_at_replacement,
        reason: actionForm.replace_reason || null,
    });
    showReplace.value = false;
    await loadTyre();
}

async function handleRemove() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId) {
        return;
    }
    await removeTyreInstallation(instId, { removed_at: new Date().toISOString().slice(0, 10) });
    await loadTyre();
}

async function handleScrap() {
    if (!confirm('Mark this tyre as scrapped?')) {
        return;
    }
    await scrapTyre(tyre.value.id, { scrapped_at: new Date().toISOString().slice(0, 10) });
    await loadTyre();
}

watch(showReplace, async (open) => {
    if (open) {
        await loadStockTyres();
        await loadReplacePreview();
    }
});

onMounted(async () => {
    vehicles.value = await listAllVehicles();
    await loadTyre();
});
</script>
