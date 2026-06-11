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
                    @click="openInstall"
                >
                    Install
                </button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-warning btn-sm"
                    @click="openRotate"
                >
                    Rotate
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="openRetread">Retread</button>
                <button
                    v-if="tyre.active_installation"
                    type="button"
                    class="btn btn-outline-success btn-sm"
                    @click="openReplace"
                >
                    Replace
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" @click="openDamage">Damage</button>
                <button
                    v-if="tyre.status !== 'SCRAPPED' && !tyre.active_installation"
                    type="button"
                    class="btn btn-outline-dark btn-sm"
                    @click="openScrap"
                >
                    Scrap / Sell
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
                            <div class="col-md-4"><span class="text-muted">Type</span><br />{{ formatTyreType(tyre.tyre_type) }}</div>
                            <div class="col-md-4">
                                <span class="text-muted">Status</span><br />
                                <span class="badge" :class="tyreStatusBadgeClass(tyre.status)">
                                    {{ formatTyreStatus(tyre.status) }}
                                </span>
                            </div>
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
                                <div class="small text-muted mt-2">
                                    <div>Prior usage (stock): {{ lifeSummary.stock_km ?? 0 }} km</div>
                                    <div v-if="tyre.active_installation">
                                        Current truck stint: {{ lifeSummary.active_stint_km ?? 0 }} km
                                    </div>
                                </div>
                                <div v-if="lifeSummary.is_early_wear_risk" class="alert alert-warning py-2 mt-2 mb-0 small">
                                    Tyre has most of its life left — avoid early scrap; check tread before removing.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Warranty</span><br />
                                <span class="badge" :class="warrantyBadgeClass(tyre)">{{ formatWarrantyLabel(tyre) }}</span>
                            </div>
                            <div v-if="tyre.warranty_valid_until" class="col-md-4">
                                <span class="text-muted">Valid Until</span><br />{{ formatWarrantyDate(tyre.warranty_valid_until) }}
                            </div>
                            <div v-if="tyre.warranty_km != null && tyre.warranty_km > 0" class="col-md-4">
                                <span class="text-muted">Warranty KM Limit</span><br />{{ formatWarrantyKm(tyre.warranty_km) }}
                            </div>
                            <div v-if="tyre.warranty_details?.trim()" class="col-12">
                                <span class="text-muted">Warranty Notes</span><br />{{ tyre.warranty_details }}
                            </div>
                            <template v-if="tyre.status === 'SCRAPPED'">
                                <div class="col-md-4">
                                    <span class="text-muted">Scrapped on</span><br />{{ tyre.scrapped_at || '—' }}
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Scrap sale amount</span><br />
                                    {{ tyre.scrap_sale_amount != null ? `₹${Number(tyre.scrap_sale_amount).toLocaleString('en-IN')}` : '—' }}
                                </div>
                                <div v-if="tyre.scrap_notes?.trim()" class="col-12">
                                    <span class="text-muted">Scrap notes</span><br />{{ tyre.scrap_notes }}
                                </div>
                            </template>
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
                                    Since {{ tyre.active_installation.installed_at }}
                                </div>
                                <div class="small mt-1">
                                    KM on truck: <strong>{{ tyre.active_installation.stint_km ?? 0 }}</strong>
                                </div>
                                <div v-if="canManage" class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openUpdateKm">
                                        Update KM
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" @click="openRemove">
                                        Remove from truck
                                    </button>
                                </div>
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
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <strong>
                                    <span class="text-muted fw-normal me-1">Step {{ i + 1 }}.</span>
                                    {{ event.title }}
                                </strong>
                                <span class="text-muted small text-nowrap">{{ event.date }}</span>
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
            :form-error="formError"
            :saving="actionSaving"
            @close="closeInstall"
            @submit="submitInstall"
        >
            <div class="mb-2">
                <label class="form-label" for="installTyre_vehicle">Truck</label>
                <SearchableSelect
                    input-id="installTyre_vehicle"
                    v-model="actionForm.vehicle_id"
                    :items="vehicleSelectOptions"
                    placeholder="Select truck"
                    search-placeholder="Search truck number…"
                    empty-message="No truck found."
                />
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
            <div v-if="lifeSummary" class="alert alert-info small py-2">
                <strong>{{ installLifeLabel }}</strong> — {{ lifeSummary.stock_km ?? 0 }} km used
                ({{ lifeSummary.life_used_percent }}% of {{ lifeSummary.expected_life_km }} km life)
            </div>
            <div class="mb-2">
                <label class="form-label">Installation Date</label>
                <input v-model="actionForm.installed_at" type="date" class="form-control" required />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showReplace"
            title="Replace Tyre on Truck"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeReplace"
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
                <label class="form-label">KM on truck *</label>
                <input
                    v-model.number="actionForm.stint_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    class="form-control"
                    required
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
            :form-error="formError"
            :saving="actionSaving"
            @close="closeRotate"
            @submit="submitRotate"
        >
            
            <div class="mb-2">
                <label class="form-label">Previous Position</label>
                <input :value="tyre?.active_installation?.position_label" class="form-control" disabled />
            </div>
            <div class="mb-2">
                <label class="form-label">New Position</label>
                <select
                    v-model="actionForm.new_position"
                    class="form-select"
                    required
                    :class="{ 'is-invalid': hasFieldError(fieldErrors, 'new_position') }"
                    :disabled="loadingVehiclePositions || rotatePositionOptions.length === 0"
                >
                    <option v-if="rotatePositionOptions.length === 0" value="" disabled>
                        No other positions on this truck
                    </option>
                    <option v-for="p in rotatePositionOptions" :key="p.value" :value="p.value">
                        {{ rotateOptionLabel(p) }}
                    </option>
                </select>
                <div v-if="fieldErrorMessage(fieldErrors, 'new_position')" class="invalid-feedback d-block">
                    {{ fieldErrorMessage(fieldErrors, 'new_position') }}
                </div>
            </div>
            <div v-if="selectedRotateTarget?.occupied" class="alert alert-warning small py-2 mb-2">
                Will swap with <strong>{{ selectedRotateTarget.tyre_serial }}</strong> currently at
                {{ selectedRotateTarget.label }}.
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <label class="form-label">KM on truck *</label>
                    <input
                        v-model.number="actionForm.stint_km"
                        v-no-scroll-change
                        type="number"
                        min="0"
                        class="form-control"
                        required
                    />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rotation Date</label>
                    <input v-model="actionForm.rotation_date" type="date" class="form-control" required />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rotation Cost (₹) *</label>
                    <input
                        v-model.number="actionForm.rotation_cost"
                        v-no-scroll-change
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        :class="{ 'is-invalid': hasFieldError(fieldErrors, 'cost') }"
                        required
                    />
                    <div v-if="fieldErrorMessage(fieldErrors, 'cost')" class="invalid-feedback d-block">
                        {{ fieldErrorMessage(fieldErrors, 'cost') }}
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Reason</label>
                <input v-model="actionForm.reason" type="text" class="form-control" placeholder="Steer swap, wear balancing…" />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRetread"
            title="Record Retread"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeRetread"
            @submit="submitRetread"
        >
            <div class="mb-2">
                <label class="form-label">Vendor</label>
                <input v-model="actionForm.vendor" type="text" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Cost</label>
                <input v-model.number="actionForm.cost" v-no-scroll-change type="number" min="0" step="0.01" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Date</label>
                <input v-model="actionForm.retread_date" type="date" class="form-control" required />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showRemove"
            title="Remove Tyre from Truck"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeRemove"
            @submit="submitRemove"
        >
            <div class="mb-2">
                <label class="form-label">Remove date</label>
                <input v-model="actionForm.removed_at" type="date" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">KM on truck *</label>
                <input
                    v-model.number="actionForm.stint_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    class="form-control"
                    required
                    @input="loadRemovePreview"
                />
            </div>
            <div v-if="removePreview" class="alert py-2 small" :class="removePreview.level === 'warning' ? 'alert-warning' : 'alert-info'">
                {{ removePreview.message }}
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showUpdateKm"
            title="Update KM on Truck"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeUpdateKm"
            @submit="submitUpdateKm"
        >
            <div class="mb-2">
                <label class="form-label">KM on truck *</label>
                <input
                    v-model.number="actionForm.stint_km"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    class="form-control"
                    required
                />
            </div>
            <div v-if="lifeSummary" class="alert alert-info small py-2 mb-0">
                Life will update to about
                <strong>{{ previewLifePercent(actionForm.stint_km) }}%</strong>
                after saving.
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showScrap"
            title="Scrap / Sell Tyre"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeScrap"
            @submit="submitScrap"
        >
            <div v-if="lifeSummary" class="alert alert-secondary small py-2">
                <div><strong>Total KM used:</strong> {{ lifeSummary.total_km_used }} km</div>
                <div><strong>Life used:</strong> {{ lifeSummary.life_used_percent }}%</div>
                <div><strong>Remaining:</strong> {{ lifeSummary.remaining_life_km }} km</div>
            </div>
            <div class="mb-2">
                <label class="form-label">Scrap date</label>
                <input v-model="actionForm.scrapped_at" type="date" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Sale amount (recovery)</label>
                <input
                    v-model.number="actionForm.scrap_sale_amount"
                    v-no-scroll-change
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    placeholder="Amount received from scrap dealer"
                />
            </div>
            <div class="mb-2">
                <label class="form-label">Notes</label>
                <textarea
                    v-model="actionForm.scrap_notes"
                    class="form-control"
                    rows="2"
                    placeholder="Buyer name, condition, etc."
                />
            </div>
        </TyreActionModal>

        <TyreActionModal
            v-if="showDamage"
            title="Record Damage"
            :form-error="formError"
            :saving="actionSaving"
            @close="closeDamage"
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
                <input v-model.number="actionForm.km_at_occurrence" v-no-scroll-change type="number" min="0" class="form-control" />
            </div>
            <div class="mb-2">
                <label class="form-label">Description</label>
                <input v-model="actionForm.damage_description" type="text" class="form-control" placeholder="Puncture, burst…" />
            </div>
            <div class="mb-2">
                <label class="form-label">Repair Cost</label>
                <input v-model.number="actionForm.repair_cost" v-no-scroll-change type="number" min="0" step="0.01" class="form-control" />
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
    updateTyreInstallationStintKm,
    replaceTyre,
    retreadTyre,
    rotateTyre,
    scrapTyre,
} from '../../api/tyres';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import TyreActionModal from '../../components/tyres/TyreActionModal.vue';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import { fieldErrorMessage, hasFieldError } from '../../utils/formErrors';
import {
    formatDamageType,
    formatTyreStatus,
    formatTyreType,
    formatWarrantyLabel,
    tyreStatusBadgeClass,
    warrantyBadgeClass,
} from '../../utils/tyreDisplay';

function formatWarrantyDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatWarrantyKm(value) {
    return `${Number(value).toLocaleString('en-IN')} km`;
}

const route = useRoute();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const actionSaving = ref(false);

const tyre = ref(null);
const lifeSummary = ref(null);
const timeline = ref([]);
const loading = ref(true);
const activeTab = ref('timeline');
const vehicles = ref([]);
const vehicleSelectOptions = computed(() =>
    vehicles.value.map((vehicle) => ({
        id: vehicle.id,
        label: vehicle.registration_number,
    })),
);
const stockTyres = ref([]);
const showInstall = ref(false);
const showRotate = ref(false);
const showRetread = ref(false);
const showDamage = ref(false);
const showReplace = ref(false);
const showRemove = ref(false);
const showUpdateKm = ref(false);
const showScrap = ref(false);
const replacePreview = ref(null);
const removePreview = ref(null);

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
            tyre_serial: slot.tyre?.serial_number ?? null,
        })),
);

const selectedRotateTarget = computed(() =>
    rotatePositionOptions.value.find((option) => option.value === actionForm.new_position) ?? null,
);

const installLifeLabel = computed(() => {
    const stockKm = lifeSummary.value?.stock_km ?? 0;

    return stockKm > 0 ? 'Used tyre' : 'New tyre';
});

function todayIsoDate() {
    return new Date().toISOString().slice(0, 10);
}

function createActionFormDefaults() {
    return {
        vehicle_id: '',
        position_code: '',
        installed_at: todayIsoDate(),
        new_position: 'FRONT_RIGHT',
        rotation_date: todayIsoDate(),
        rotation_cost: 0,
        reason: '',
        vendor: '',
        cost: 0,
        retread_date: todayIsoDate(),
        damage_type: 'PUNCTURE',
        occurred_at: todayIsoDate(),
        repair_cost: null,
        damage_description: '',
        km_at_occurrence: null,
        stint_km: 0,
        removed_at: todayIsoDate(),
        new_tyre_id: '',
        replaced_at: todayIsoDate(),
        replace_reason: '',
        scrapped_at: todayIsoDate(),
        scrap_sale_amount: null,
        scrap_notes: '',
    };
}

const actionForm = reactive(createActionFormDefaults());

function resetActionForm() {
    Object.assign(actionForm, createActionFormDefaults());
}

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
            r.installed_at,
            r.removed_at ?? '—',
            r.running_km ?? '—',
            r.removal_recommendation ?? '—',
        ]);
    }
    if (activeTab.value === 'rotations') {
        return (tyre.value.rotations ?? []).map((r) => [
            r.vehicle_registration,
            r.rotation_date,
            r.previous_position_label ?? r.previous_position,
            r.new_position_label ?? r.new_position,
            r.cost != null ? `₹${r.cost}` : '—',
            r.reason ?? '—',
        ]);
    }
    if (activeTab.value === 'retreads') {
        return (tyre.value.retreads ?? []).map((r) => [r.retread_date, r.vendor, r.cost, r.condition_after ?? '—']);
    }
    if (activeTab.value === 'damages') {
        return (tyre.value.damages ?? []).map((r) => [
            formatDamageType(r.damage_type),
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
        installations: ['Truck', 'Position', 'Installed', 'Removed', 'Ran KM', 'Advice'],
        rotations: ['Truck', 'Date', 'From', 'To', 'Cost', 'Reason'],
        retreads: ['Date', 'Vendor', 'Cost', 'Condition'],
        damages: ['Type', 'Truck', 'Position', 'KM', 'Repair', 'Fixed'],
        expenses: ['Type', 'Date', 'Amount', 'Notes'],
    };
    return map[activeTab.value] ?? [];
});

function rotateOptionLabel(option) {
    if (!option.occupied) {
        return option.label;
    }

    return `${option.label} (swap with ${option.tyre_serial ?? 'other tyre'})`;
}

function closeActionModal(modalRef) {
    modalRef.value = false;
    clearErrors();
    resetActionForm();
}

function openInstall() {
    clearErrors();
    resetActionForm();
    showInstall.value = true;
}

function openRotate() {
    clearErrors();
    resetActionForm();
    showRotate.value = true;
}

function openRetread() {
    clearErrors();
    resetActionForm();
    showRetread.value = true;
}

function openDamage() {
    clearErrors();
    resetActionForm();
    showDamage.value = true;
}

function openReplace() {
    clearErrors();
    resetActionForm();
    actionForm.stint_km = tyre.value?.active_installation?.stint_km ?? 0;
    showReplace.value = true;
}

function openRemove() {
    clearErrors();
    resetActionForm();
    actionForm.stint_km = tyre.value?.active_installation?.stint_km ?? 0;
    removePreview.value = null;
    showRemove.value = true;
    loadRemovePreview();
}

function openUpdateKm() {
    clearErrors();
    resetActionForm();
    actionForm.stint_km = tyre.value?.active_installation?.stint_km ?? 0;
    showUpdateKm.value = true;
}

function openScrap() {
    clearErrors();
    resetActionForm();
    showScrap.value = true;
}

function closeInstall() {
    closeActionModal(showInstall);
}

function closeRotate() {
    closeActionModal(showRotate);
}

function closeRetread() {
    closeActionModal(showRetread);
}

function closeDamage() {
    closeActionModal(showDamage);
}

function closeReplace() {
    closeActionModal(showReplace);
}

function closeRemove() {
    closeActionModal(showRemove);
    removePreview.value = null;
}

function closeUpdateKm() {
    closeActionModal(showUpdateKm);
}

function closeScrap() {
    closeActionModal(showScrap);
}

function finishTyreAction(modalRef) {
    modalRef.value = false;
    clearErrors();
    resetActionForm();
}

async function runTyreAction(action) {
    clearErrors();
    actionSaving.value = true;

    try {
        await action();
    } catch (error) {
        const mappedError = mapTyreActionApiError(error);
        applyApiError(mappedError, 'Unable to save. Please check the form and try again.');
    } finally {
        actionSaving.value = false;
    }
}

function mapTyreActionApiError(error) {
    const data = error?.response?.data;

    if (!data?.errors) {
        return error;
    }

    const errors = { ...data.errors };

    if (errors.position_code && !errors.new_position) {
        errors.new_position = errors.position_code;
        delete errors.position_code;
    }

    return {
        ...error,
        response: {
            ...error.response,
            data: {
                ...data,
                errors,
            },
        },
    };
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

function previewLifePercent(stintKm) {
    const expected = lifeSummary.value?.expected_life_km ?? tyre.value?.expected_life_km ?? 40000;
    const stockKm = lifeSummary.value?.stock_km ?? 0;
    const total = stockKm + (Number(stintKm) || 0);

    if (expected <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((total / expected) * 1000) / 10);
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
            const firstTarget = rotatePositionOptions.value[0];
            actionForm.new_position = firstTarget?.value ?? '';
        }
    } finally {
        loading.value = false;
    }
}

async function loadReplacePreview() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId) {
        return;
    }
    const res = await previewTyreRemoval(instId, actionForm.stint_km);
    replacePreview.value = res.data ?? null;
}

async function loadRemovePreview() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId) {
        return;
    }
    const res = await previewTyreRemoval(instId, actionForm.stint_km);
    removePreview.value = res.data ?? null;
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
    if (!open) {
        return;
    }

    clearErrors();
    actionForm.stint_km = tyre.value?.active_installation?.stint_km ?? 0;

    if (tyre.value?.active_installation?.vehicle_id) {
        await loadVehiclePositions(tyre.value.active_installation.vehicle_id);
        const firstTarget = rotatePositionOptions.value[0];
        actionForm.new_position = firstTarget?.value ?? '';
    }
});

async function loadStockTyres() {
    const res = await listTyres({ perPage: 100, status: 'IN_STOCK' });
    stockTyres.value = res.data.filter((t) => t.id !== tyre.value?.id);
}

async function submitInstall() {
    await runTyreAction(async () => {
        await installTyre(tyre.value.id, {
            vehicle_id: actionForm.vehicle_id,
            position_code: actionForm.position_code,
            installed_at: actionForm.installed_at,
        });
        finishTyreAction(showInstall);
        await loadTyre();
    });
}

async function submitRotate() {
    await runTyreAction(async () => {
        await rotateTyre(tyre.value.id, {
            rotation_date: actionForm.rotation_date,
            previous_position: tyre.value.active_installation.position,
            new_position: actionForm.new_position,
            cost: actionForm.rotation_cost,
            stint_km: actionForm.stint_km,
            reason: actionForm.reason || null,
        });
        finishTyreAction(showRotate);
        activeTab.value = 'rotations';
        await loadTyre();
    });
}

async function submitRetread() {
    await runTyreAction(async () => {
        await retreadTyre(tyre.value.id, {
            vendor: actionForm.vendor,
            cost: actionForm.cost,
            retread_date: actionForm.retread_date,
        });
        finishTyreAction(showRetread);
        activeTab.value = 'retreads';
        await loadTyre();
    });
}

async function submitDamage() {
    await runTyreAction(async () => {
        await recordTyreDamage(tyre.value.id, {
            damage_type: actionForm.damage_type,
            occurred_at: actionForm.occurred_at,
            repair_cost: actionForm.repair_cost || null,
            description: actionForm.damage_description || null,
            km_at_occurrence: actionForm.km_at_occurrence || null,
        });
        finishTyreAction(showDamage);
        activeTab.value = 'damages';
        await loadTyre();
    });
}

async function submitReplace() {
    await runTyreAction(async () => {
        await replaceTyre({
            vehicle_id: tyre.value.active_installation.vehicle_id,
            position_code: tyre.value.active_installation.position,
            old_tyre_id: tyre.value.id,
            new_tyre_id: actionForm.new_tyre_id || null,
            replaced_at: actionForm.replaced_at,
            stint_km: actionForm.stint_km,
            reason: actionForm.replace_reason || null,
        });
        finishTyreAction(showReplace);
        await loadTyre();
    });
}

async function submitRemove() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId) {
        return;
    }

    await runTyreAction(async () => {
        await removeTyreInstallation(instId, {
            removed_at: actionForm.removed_at,
            stint_km: actionForm.stint_km,
        });
        finishTyreAction(showRemove);
        removePreview.value = null;
        await loadTyre();
    });
}

async function submitUpdateKm() {
    const instId = tyre.value?.active_installation?.installation_id ?? tyre.value?.active_installation?.id;
    if (!instId) {
        return;
    }

    await runTyreAction(async () => {
        await updateTyreInstallationStintKm(instId, { stint_km: actionForm.stint_km });
        finishTyreAction(showUpdateKm);
        await loadTyre();
    });
}

async function submitScrap() {
    await runTyreAction(async () => {
        await scrapTyre(tyre.value.id, {
            scrapped_at: actionForm.scrapped_at,
            scrap_sale_amount: actionForm.scrap_sale_amount === '' || actionForm.scrap_sale_amount == null
                ? null
                : actionForm.scrap_sale_amount,
            scrap_notes: actionForm.scrap_notes || null,
        });
        finishTyreAction(showScrap);
        activeTab.value = 'timeline';
        await loadTyre();
    });
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
