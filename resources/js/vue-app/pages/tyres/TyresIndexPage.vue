<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing tyres.
        </div>

        <AppIndexListCard
            title="Tyre Management"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading tyres…"
        >
            <template #toolbar>
                <RouterLink
                    v-if="vehicleFilter"
                    :to="{ name: 'vehicles.tyres', params: { vehicleId: vehicleFilter } }"
                    class="btn btn-sm btn-light border-0 bg-white shadow-sm text-dark d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Tyre Positions"
                    aria-label="Tyre Positions"
                >
                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-1">Positions</span>
                </RouterLink>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Bulk Upload Tyres"
                    aria-label="Bulk Upload Tyres"
                    @click="showBulkUpload = true"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5"/>
                        <path fill-rule="evenodd" d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708z"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Bulk </span>Upload</span>
                </button>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Add Tyre"
                    aria-label="Add Tyre"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Add </span>Tyre</span>
                </button>
            </template>

            <template #filters>
                <div class="d-flex d-md-none align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                    <span class="fw-bold text-uppercase text-dark small text-nowrap" style="letter-spacing: 0.05em; font-size: 0.8125rem; white-space: nowrap;">SEARCH &amp; FILTERS</span>
                    <button
                        type="button"
                        class="btn btn-sm rounded-pill border bg-white text-dark shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold"
                        style="font-size: 0.8125rem;"
                        :class="{ 'bg-light border-secondary': filtersOpen }"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>
                        <span>Filters</span>
                        <i
                            class="bi ms-1 text-muted"
                            :class="filtersOpen ? 'bi-chevron-up' : 'bi-chevron-down'"
                            style="font-size: 0.75rem;"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div v-show="filtersOpen" class="tyres-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="tyres-filters-panel"
                        :loading="loading"
                        @search="applyFilters"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label" for="tyresIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="search"
                                input-id="tyresIndex_search"
                                placeholder="Tyre number, brand, size…"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter.prevent="applyFilters"
                            />
                        </div>
                        <div class="filter-truck-col">
                            <label class="form-label" for="tyresIndex_vehicle">Truck</label>
                            <VehicleSelect
                                input-id="tyresIndex_vehicle"
                                v-model="vehicleFilter"
                                :vehicles="vehicles"
                                placeholder="All trucks"
                                :disabled="loading"
                            />
                        </div>
                        <div class="filter-status-col">
                            <label class="form-label">Status</label>
                            <AppSelect
                                v-model="statusFilter"
                                :options="tyreStatuses"
                                placeholder="All statuses"
                                search-placeholder="Search status…"
                                :disabled="loading"
                            />
                        </div>
                    </AppIndexCollapsibleFilters>
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap" style="min-width: 6.5rem;">Tyre #</th>
                        <th class="text-nowrap" style="min-width: 5.5rem;">Brand</th>
                        <th class="text-nowrap" style="min-width: 5.5rem;">Size</th>
                        <th class="text-nowrap" style="min-width: 5rem;">Type</th>
                        <th class="text-nowrap" style="min-width: 5.5rem;">Status</th>
                        <th class="text-nowrap" style="min-width: 6rem;">Life used</th>
                        <th class="text-nowrap" style="min-width: 5rem;">Warranty</th>
                        <th class="text-nowrap" style="min-width: 8rem;">Truck / Position</th>
                        <th class="text-nowrap" style="min-width: 5.5rem;">Purchase</th>
                        <th v-if="canManage" class="app-index-actions-cell text-center text-nowrap" style="width: 120px; min-width: 90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!filtersApplied" class="app-index-empty-row">
                        <td :colspan="canManage ? 10 : 9" class="text-center text-muted py-4">
                            Select filters and click Search to view tyres.
                        </td>
                    </tr>
                    <tr v-else-if="tyres.length === 0" class="app-index-empty-row">
                        <td :colspan="canManage ? 10 : 9" class="text-center text-muted py-4">
                            No tyres found for selected filters.
                        </td>
                    </tr>
                    <tr v-for="tyre in tyres" :key="tyre.id">
                            <td>
                                <RouterLink
                                    :to="{ name: 'tyres.show', params: { id: tyre.id }, query: listFilterQuery }"
                                    class="fw-semibold text-decoration-none"
                                >
                                    {{ tyre.serial_number }}
                                </RouterLink>
                            </td>
                            <td>{{ tyre.brand || '—' }}</td>
                            <td>{{ tyre.size || '—' }}</td>
                            <td>{{ formatTyreType(tyre.tyre_type) }}</td>
                            <td>
                                <span class="badge" :class="tyreStatusBadgeClass(tyre.status)">
                                    {{ formatTyreStatus(tyre.status) }}
                                </span>
                            </td>
                            <td class="small" style="min-width: 110px">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>{{ estimateLifePercent(tyre) }}%</span>
                                </div>
                                
                            </td>
                            <td class="small">
                                <template v-if="hasTyreWarranty(tyre)">
                                    <span class="badge mb-1" :class="warrantyBadgeClass(tyre)">Yes</span>
                                    <div class="text-muted" :title="formatWarrantyTooltip(tyre)">
                                        {{ formatWarrantySummary(tyre) }}
                                    </div>
                                </template>
                                <span v-else class="badge" :class="warrantyBadgeClass(tyre)">No</span>
                            </td>
                            <td>
                                <template v-if="tyre.active_installation">
                                    {{ tyre.active_installation.vehicle_registration }}
                                    ({{ tyre.active_installation.position_label }})
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>{{ formatMoney(tyre.purchase_cost) }}</td>
                            <td v-if="canManage" class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton action="view" @click="goToDetail(tyre.id)" />
                                    <AppCrudActionButton action="edit" @click="openEdit(tyre)" />
                                </AppTableActions>
                            </td>
                        </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="tyres"
                    aria-label="Tyres pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <AppModal
            :show="showModal && !showSaveConfirm"
            :loading="saving"
            :form-error="formError"
            as-form
            content-class="tyre-form-modal__content"
            body-class="tyre-form-body"
            footer-class="tyre-form-footer"
            @close="closeModal"
            @submit="requestSaveConfirm('save')"
        >
            <template #header>
                <div>
                    <h5 class="modal-title mb-0">{{ editing ? 'Edit Tyre' : 'Add Tyre' }}</h5>
                    <p v-if="modalHeaderMetaText" class="tyre-form-modal__meta mb-0 mt-1">
                        {{ modalHeaderMetaText }}
                    </p>
                </div>
            </template>

            <div class="tyre-form-grid">
                                <div>
                                    <label class="form-label" for="tyreForm_serial_number">Tyre Number *</label>
                                    <input
                                        id="tyreForm_serial_number"
                                        ref="serialNumberInputRef"
                                        v-model="form.serial_number"
                                        type="text"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': fieldErrors.serial_number }"
                                        autocomplete="off"
                                        required
                                    />
                                    <div v-if="fieldErrors.serial_number" class="invalid-feedback d-block">
                                        {{ fieldErrors.serial_number[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_brand">Brand *</label>
                                    <input
                                        id="tyreForm_brand"
                                        v-model="form.brand"
                                        type="text"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': fieldErrors.brand }"
                                        autocomplete="off"
                                        required
                                    />
                                    <div v-if="fieldErrors.brand" class="invalid-feedback d-block">
                                        {{ fieldErrors.brand[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_size">Size *</label>
                                    <input
                                        id="tyreForm_size"
                                        v-model="form.size"
                                        type="text"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': fieldErrors.size }"
                                        placeholder="10.00-20"
                                        required
                                    />
                                    <div v-if="fieldErrors.size" class="invalid-feedback d-block">
                                        {{ fieldErrors.size[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_purchase_date">Purchase Date *</label>
                                    <AppDateInput
                                        input-id="tyreForm_purchase_date"
                                        v-model="form.purchase_date"
                                        :max="maxTyreActionDate"
                                        :invalid="!!fieldErrors.purchase_date"
                                        size="sm"
                                        required
                                    />
                                    <div v-if="fieldErrors.purchase_date" class="invalid-feedback d-block">
                                        {{ fieldErrors.purchase_date[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_expected_life_km">Expected Tyre Life (KM) *</label>
                                    <input
                                        id="tyreForm_expected_life_km"
                                        v-model.number="form.expected_life_km"
                                        v-no-scroll-change
                                        type="number"
                                        min="1000"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': fieldErrors.expected_life_km }"
                                        placeholder="e.g. 40,000"
                                        required
                                    />
                                    <div v-if="fieldErrors.expected_life_km" class="invalid-feedback d-block">
                                        {{ fieldErrors.expected_life_km[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_current_km">Initial Running KM <span class="text-muted fw-normal">(optional)</span></label>
                                    <input
                                        id="tyreForm_current_km"
                                        v-model.number="form.current_km"
                                        v-no-scroll-change
                                        type="number"
                                        min="0"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': fieldErrors.current_km }"
                                        placeholder="0"
                                    />
                                    <div v-if="fieldErrors.current_km" class="invalid-feedback d-block">
                                        {{ fieldErrors.current_km[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_purchase_cost">Purchase Cost <span class="text-muted fw-normal">(optional)</span></label>
                                    <input
                                        id="tyreForm_purchase_cost"
                                        v-model="form.purchase_cost"
                                        v-no-scroll-change
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control form-control-sm"
                                        placeholder="0"
                                        autocomplete="off"
                                        inputmode="decimal"
                                        :class="{ 'is-invalid': fieldErrors.purchase_cost }"
                                        @keydown="blockNegativeAmountKey"
                                        @paste="onPositiveAmountPaste($event, 'purchase_cost')"
                                        @blur="normalizePurchaseCostField"
                                    />
                                    <div v-if="fieldErrors.purchase_cost" class="invalid-feedback d-block">
                                        {{ fieldErrors.purchase_cost[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_type">Type *</label>
                                    <AppSelect
                                        input-id="tyreForm_type"
                                        v-model="form.tyre_type"
                                        :options="tyreTypeOptions"
                                        placeholder="Select type"
                                        search-placeholder="Search type…"
                                        :invalid="Boolean(fieldErrors.tyre_type)"
                                        :error-message="fieldErrors.tyre_type?.[0] ?? ''"
                                    />
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_warranty_until">Warranty Valid Until <span class="text-muted fw-normal">(optional)</span></label>
                                    <AppDateInput
                                        input-id="tyreForm_warranty_until"
                                        v-model="form.warranty_valid_until"
                                        :invalid="!!fieldErrors.warranty_valid_until"
                                        size="sm"
                                    />
                                    <div v-if="fieldErrors.warranty_valid_until" class="invalid-feedback d-block">
                                        {{ fieldErrors.warranty_valid_until[0] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="tyreForm_warranty_km">Warranty KM Limit <span class="text-muted fw-normal">(optional)</span></label>
                                    <input
                                        id="tyreForm_warranty_km"
                                        v-model="form.warranty_km"
                                        type="text"
                                        inputmode="numeric"
                                        class="form-control form-control-sm"
                                        placeholder="e.g. 40,000"
                                        autocomplete="off"
                                        :class="{ 'is-invalid': fieldErrors.warranty_km }"
                                    />
                                    <div v-if="fieldErrors.warranty_km" class="invalid-feedback d-block">
                                        {{ fieldErrors.warranty_km[0] }}
                                    </div>
                                </div>
                                <div class="tyre-form-grid__full">
                                    <label class="form-label" for="tyreForm_notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                    <textarea
                                        id="tyreForm_notes"
                                        v-model="form.notes"
                                        class="form-control form-control-sm"
                                        rows="3"
                                        placeholder="Dealer name, claim terms, etc."
                                    />
                                </div>
                            </div>

            <template #footer>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="closeModal">Cancel</button>
                <AppButton
                    v-if="!editing"
                    type="button"
                    variant="success"
                    outline
                    size="sm"
                    label="Save & Add Another"
                    :loading="saving"
                    @click="requestSaveConfirm('saveAndNext')"
                />
                <AppButton type="submit" variant="success" size="sm" label="Save" :loading="saving" />
            </template>
        </AppModal>

        <TyreActionConfirmModal
            :show="showSaveConfirm"
            :title="saveConfirmSummary?.title ?? 'Confirm Tyre'"
            :message="saveConfirmSummary?.message ?? ''"
            :loading="saving"
            :confirm-label="saveConfirmSummary?.confirmLabel ?? 'Yes, Save Tyre'"
            :confirm-variant="editing ? 'warning' : 'success'"
            :icon-tone="saveConfirmSummary?.iconTone ?? 'primary'"
            :tyre-serial="saveConfirmSummary?.tyreSerial ?? ''"
            :truck-number="saveConfirmSummary?.truckNumber ?? ''"
            :position="saveConfirmSummary?.position ?? ''"
            :show-truck="saveConfirmSummary?.showTruck ?? false"
            :items="saveConfirmSummary?.items ?? []"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSaveTyre"
        />

        <BulkUploadModal
            v-model:show="showBulkUpload"
            title="Bulk Upload Tyres"
            upload-url="/app/api/tyres/bulk-upload"
            template-download-url="/app/api/tyres/bulk-upload/template"
            template-file-name="tyres_bulk_upload_template.xlsx"
            :columns="bulkUploadColumns"
            @success="loadTyres(1)"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { createTyre, getTyre, listTyres, updateTyre } from '../../api/tyres';
import AppModal from '../../components/ui/AppModal.vue';
import TyreActionConfirmModal from '../../components/tyres/TyreActionConfirmModal.vue';
import BulkUploadModal from '../../components/ui/BulkUploadModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import VehicleSelect from '../../components/ui/VehicleSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import {
    TYRE_STATUS_FILTER_ALL,
    TYRE_STATUSES,
    TYRE_TYPES,
    formatTyreStatus,
    formatTyreType,
    formatWarrantySummary,
    formatWarrantyTooltip,
    hasTyreWarranty,
    tyreStatusBadgeClass,
    warrantyBadgeClass,
} from '../../utils/tyreDisplay';
import { buildTyreSaveConfirmSummary } from '../../utils/tyre-save-confirm-info';
import { toDateInputValue } from '../../utils/datetime';

const maxTyreActionDate = toDateInputValue();

const DEFAULT_TYRE_TYPE = 'RADIAL';

function estimateLifePercent(tyre) {
    const expected = Number(tyre?.expected_life_km ?? 40000);
    const used = Number(tyre?.current_km ?? 0);

    if (expected <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((used / expected) * 100));
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

function optionalNumber(value) {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    const normalized = String(value).replace(/[₹,\s]/g, '');
    const parsed = Number(normalized);

    return Number.isNaN(parsed) ? null : parsed;
}

function numberFieldDisplay(value) {
    return value !== null && value !== undefined ? String(value) : '';
}

function blockNegativeAmountKey(event) {
    if (event.key === '-' || event.key === 'Minus' || event.key === 'e' || event.key === 'E' || event.key === '+') {
        event.preventDefault();
    }
}

function purchaseCostForPayload(value) {
    const parsed = optionalNumber(value);

    return parsed === null ? 0 : parsed;
}

function normalizePurchaseCostField() {
    const parsed = optionalNumber(form.purchase_cost);
    form.purchase_cost = parsed === null ? 0 : parsed;
}

function onPositiveAmountPaste(event, fieldKey) {
    const text = (event.clipboardData?.getData('text') ?? '').trim();

    if (text.includes('-') || text.includes('+') || (text !== '' && Number(text) < 0)) {
        event.preventDefault();
        return;
    }

    const parsed = optionalNumber(text);

    if (text !== '' && parsed === null) {
        event.preventDefault();
        return;
    }

    form[fieldKey] = text === '' ? (fieldKey === 'purchase_cost' ? 0 : '') : String(parsed);
    event.preventDefault();
}

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));

const tyreStatuses = TYRE_STATUSES;
const tyreTypeOptions = TYRE_TYPES;

const tyres = ref([]);
const loading = ref(false);
const filtersApplied = ref(false);
const filtersOpen = ref(false);
const saving = ref(false);
const search = ref('');
const statusFilter = ref('IN_STOCK');
const vehicleFilter = ref('');
let syncingFilters = false;
let tyresRequestId = 0;
const vehicles = ref([]);
const showModal = ref(false);
const showBulkUpload = ref(false);
const showSaveConfirm = ref(false);

const bulkUploadColumns = [
    { name: 'serial_number', label: 'Tyre Serial Number', required: true, description: 'Unique tyre serial number' },
    { name: 'brand', label: 'Brand', required: true, description: 'Tyre manufacturer / brand (e.g. MRF, Apollo)' },
    { name: 'size', label: 'Size', required: true, description: 'Tyre size specification (e.g. 295/80R22.5)' },
    { name: 'purchase_date', label: 'Purchase Date', required: true, description: 'Date of tyre purchase (YYYY-MM-DD)' },
    { name: 'purchase_cost', label: 'Purchase Cost', required: false, description: 'Purchase price / cost in ₹' },
    { name: 'expected_life_km', label: 'Expected Life KM', required: false, description: 'Expected life mileage (default: 40000)' },
    { name: 'tyre_type', label: 'Tyre Type', required: false, description: 'RADIAL, BIAS, TUBELESS, TUBED, or OTHER' },
    { name: 'warranty_valid_until', label: 'Warranty Valid Until', required: false, description: 'Warranty expiry date (YYYY-MM-DD)' },
    { name: 'warranty_km', label: 'Warranty KM', required: false, description: 'Warranty limit in KM' },
    { name: 'current_km', label: 'Current KM', required: false, description: 'Current odometer / running KM (default: 0)' },
    { name: 'notes', label: 'Notes', required: false, description: 'Additional remarks or notes' },
];
const pendingSaveMode = ref('save');
const editing = ref(null);
const serialNumberInputRef = ref(null);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const { pagination, syncFromMeta } = useListPagination(10);

const form = reactive({
    serial_number: '',
    brand: '',
    size: '',
    tyre_type: DEFAULT_TYRE_TYPE,
    purchase_date: '',
    purchase_cost: 0,
    expected_life_km: 40000,
    current_km: 0,
    warranty_valid_until: '',
    warranty_km: '',
    notes: '',
});

const filtersActive = computed(
    () =>
        search.value.trim() !== '' ||
        statusFilter.value !== '' ||
        vehicleFilter.value !== '' ||
        route.query.status === TYRE_STATUS_FILTER_ALL,
);

const listFilterQuery = computed(() => {
    const query = {};

    if (search.value.trim() !== '') {
        query.search = search.value.trim();
    }

    // Persist All statuses as ALL so detail → Back to Tyres does not snap back to IN_STOCK.
    query.status = statusFilter.value || TYRE_STATUS_FILTER_ALL;

    if (vehicleFilter.value) {
        query.vehicle_id = String(vehicleFilter.value);
    }

    if (pagination.currentPage > 1) {
        query.page = String(pagination.currentPage);
    }

    return query;
});

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

const minimumRecordedKm = computed(() => Number(editing.value?.minimum_recorded_km ?? 0));

const displayStatusLabel = computed(() => {
    if (editing.value?.status) {
        return formatTyreStatus(editing.value.status);
    }

    return formatTyreStatus('IN_STOCK');
});

const modalHeaderMetaText = computed(() => {
    if (!editing.value) {
        return '';
    }

    const parts = [];
    const installation = editing.value.active_installation;

    if (installation?.vehicle_registration) {
        const truckLabel = installation.position_label
            ? `${installation.vehicle_registration} (${installation.position_label})`
            : installation.vehicle_registration;
        parts.push(`Truck: ${truckLabel}`);
    }

    parts.push(displayStatusLabel.value);

    return parts.join(' · ');
});

const saveConfirmSummary = computed(() =>
    buildTyreSaveConfirmSummary({
        editing: editing.value,
        form,
        editingTyre: editing.value,
    }),
);

function statusClearsTruckFilter(status = statusFilter.value) {
    return status === 'IN_STOCK' || status === 'SCRAPPED';
}

function clearTruckFilterIfNeeded() {
    if (!statusClearsTruckFilter()) {
        return;
    }

    if (vehicleFilter.value) {
        vehicleFilter.value = '';
    }
}

function setInstalledWhenTruckSelected() {
    if (!vehicleFilter.value) {
        return;
    }

    if (statusFilter.value !== 'INSTALLED') {
        statusFilter.value = 'INSTALLED';
    }
}

function applyFilters() {
    clearTruckFilterIfNeeded();
    filtersApplied.value = true;
    pagination.currentPage = 1;
    syncVehicleQuery();
    loadTyres(1);
}

function clearFilters() {
    syncingFilters = true;
    search.value = '';
    statusFilter.value = 'IN_STOCK';
    vehicleFilter.value = '';
    syncingFilters = false;
    pagination.currentPage = 1;
    applyFilters();
}

function restoreListFromRoute() {
    clearTruckFilterIfNeeded();
    filtersApplied.value = true;
    syncVehicleQuery();
    loadTyres(pagination.currentPage);
}

function syncVehicleQuery() {
    const nextQuery = { ...route.query };

    if (search.value.trim() !== '') {
        nextQuery.search = search.value.trim();
    } else {
        delete nextQuery.search;
    }

    if (vehicleFilter.value) {
        nextQuery.vehicle_id = String(vehicleFilter.value);
    } else {
        delete nextQuery.vehicle_id;
    }

    nextQuery.status = statusFilter.value || TYRE_STATUS_FILTER_ALL;

    if (pagination.currentPage > 1) {
        nextQuery.page = String(pagination.currentPage);
    } else {
        delete nextQuery.page;
    }

    const currentKeys = Object.keys(route.query).sort();
    const nextKeys = Object.keys(nextQuery).sort();
    const unchanged =
        currentKeys.length === nextKeys.length &&
        nextKeys.every((key) => String(route.query[key] ?? '') === String(nextQuery[key] ?? ''));

    if (unchanged) {
        return;
    }

    router.replace({ query: nextQuery });
}

function goToPage(page) {
    if (!filtersApplied.value || page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    pagination.currentPage = page;
    syncVehicleQuery();
    loadTyres(page);
}

function resetForm() {
    Object.assign(form, {
        serial_number: '',
        brand: '',
        size: '',
        tyre_type: DEFAULT_TYRE_TYPE,
        purchase_date: '',
        purchase_cost: 0,
        expected_life_km: 40000,
        current_km: 0,
        warranty_valid_until: '',
        warranty_km: '',
        notes: '',
    });
}

function openCreate() {
    editing.value = null;
    resetForm();
    clearErrors();
    showModal.value = true;
}

async function openEdit(tyre) {
    editing.value = tyre;
    clearErrors();
    Object.assign(form, {
        serial_number: tyre.serial_number,
        brand: tyre.brand ?? '',
        size: tyre.size ?? '',
        tyre_type: tyre.tyre_type || DEFAULT_TYRE_TYPE,
        purchase_date: tyre.purchase_date ?? '',
        purchase_cost: Number(tyre.purchase_cost ?? 0),
        expected_life_km: tyre.expected_life_km ?? 40000,
        current_km: tyre.current_km ?? 0,
        warranty_valid_until: tyre.warranty_valid_until ?? '',
        warranty_km: numberFieldDisplay(tyre.warranty_km),
        notes: tyre.notes ?? '',
    });
    showModal.value = true;

    try {
        const payload = await getTyre(tyre.id);
        editing.value = {
            ...tyre,
            ...(payload.tyre ?? {}),
            minimum_recorded_km: payload.life_summary?.minimum_current_km ?? 0,
        };
        form.current_km = payload.tyre?.current_km ?? form.current_km;
    } catch {
        // Keep list values if detail fetch fails.
    }
}

function closeModal() {
    showModal.value = false;
    clearErrors();
}

function goToDetail(id) {
    router.push({ name: 'tyres.show', params: { id }, query: listFilterQuery.value });
}

async function loadTyres(page = 1) {
    if (needsCompanySelection.value || !filtersApplied.value) {
        loading.value = false;

        return;
    }

    const requestId = ++tyresRequestId;
    loading.value = true;

    try {
        const response = await listTyres({
            page,
            perPage: pagination.perPage,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            vehicleId: vehicleFilter.value || undefined,
        });

        if (requestId !== tyresRequestId) {
            return;
        }

        tyres.value = response.data;
        syncFromMeta(response.meta, tyres.value.length);
        syncVehicleQuery();
    } finally {
        if (requestId === tyresRequestId) {
            loading.value = false;
        }
    }
}

function buildPayload() {
    return {
        serial_number: form.serial_number,
        brand: form.brand,
        size: form.size,
        tyre_type: form.tyre_type || DEFAULT_TYRE_TYPE,
        purchase_date: form.purchase_date || null,
        purchase_cost: purchaseCostForPayload(form.purchase_cost),
        expected_life_km: form.expected_life_km,
        current_km: form.current_km === '' || form.current_km == null ? 0 : form.current_km,
        warranty_valid_until: form.warranty_valid_until || null,
        warranty_km: optionalNumber(form.warranty_km),
        notes: form.notes || null,
    };
}

function validateForm() {
    const errors = {};

    if (!String(form.serial_number ?? '').trim()) {
        errors.serial_number = ['Tyre number is required.'];
    }

    if (!String(form.brand ?? '').trim()) {
        errors.brand = ['Brand is required.'];
    }

    if (!String(form.size ?? '').trim()) {
        errors.size = ['Size is required.'];
    }

    if (!String(form.tyre_type ?? '').trim()) {
        errors.tyre_type = ['Type is required.'];
    }

    if (!form.purchase_date) {
        errors.purchase_date = ['Please enter a valid purchase date (dd/mm/yyyy).'];
    }

    const purchaseCost = purchaseCostForPayload(form.purchase_cost);
    const purchaseCostRaw = form.purchase_cost;
    const purchaseCostTouched =
        purchaseCostRaw !== '' && purchaseCostRaw !== null && purchaseCostRaw !== undefined && Number(purchaseCostRaw) !== 0;

    if (purchaseCostTouched && optionalNumber(purchaseCostRaw) === null) {
        errors.purchase_cost = ['Enter a valid amount (numbers only, e.g. 1000).'];
    }

    if (purchaseCost < 0) {
        errors.purchase_cost = ['Purchase cost cannot be negative.'];
    }

    if (form.expected_life_km == null || form.expected_life_km === '' || Number(form.expected_life_km) < 1000) {
        errors.expected_life_km = ['Expected life must be at least 1,000 km.'];
    }

    if (Object.keys(errors).length > 0) {
        fieldErrors.value = errors;
        const firstMessage = Object.values(errors)
            .flat()
            .find((message) => typeof message === 'string' && message.length > 0);
        formError.value = firstMessage ?? 'Please complete the required fields marked with *.';
        return false;
    }

    return true;
}

async function persistTyre() {
    const payload = buildPayload();

    if (editing.value) {
        await updateTyre(editing.value.id, payload);
        return;
    }

    await createTyre(payload);
}

function requestSaveConfirm(mode = 'save') {
    clearErrors();

    if (!validateForm()) {
        return;
    }

    pendingSaveMode.value = mode;
    showSaveConfirm.value = true;
}

function cancelSaveConfirm() {
    if (saving.value) {
        return;
    }

    showSaveConfirm.value = false;
    pendingSaveMode.value = 'save';
}

async function confirmSaveTyre() {
    saving.value = true;

    const wasEditing = Boolean(editing.value);
    const createdSerial = String(form.serial_number ?? '').trim();

    try {
        await persistTyre();
        toast.success(wasEditing ? 'Tyre updated successfully.' : 'Tyre added successfully.');
        showSaveConfirm.value = false;

        if (pendingSaveMode.value === 'saveAndNext') {
            if (!wasEditing && createdSerial) {
                await showCreatedTyreOnList(createdSerial);
            } else if (filtersApplied.value) {
                await loadTyres(pagination.currentPage);
            }

            form.serial_number = '';
            await nextTick();
            serialNumberInputRef.value?.focus();
        } else {
            closeModal();

            if (!wasEditing && createdSerial) {
                await showCreatedTyreOnList(createdSerial);
            } else if (filtersApplied.value) {
                await loadTyres(pagination.currentPage);
            }
        }
    } catch (error) {
        showSaveConfirm.value = false;
        applyApiError(error, friendlyApiErrorMessage(error, 'Unable to save the tyre. Please check the form and try again.'));
    } finally {
        saving.value = false;
        pendingSaveMode.value = 'save';
    }
}

async function showCreatedTyreOnList(serialNumber) {
    syncingFilters = true;
    search.value = serialNumber;
    statusFilter.value = 'IN_STOCK';
    vehicleFilter.value = '';
    syncingFilters = false;
    filtersOpen.value = true;
    filtersApplied.value = true;
    pagination.currentPage = 1;
    syncVehicleQuery();
    await loadTyres(1);
}

function formatMoney(amount) {
    return amount != null ? `₹${Number(amount).toLocaleString('en-IN')}` : '—';
}

function applyRouteFilters() {
    syncingFilters = true;

    try {
        const { status, vehicle_id: vehicleId, search: searchQuery, page: pageQuery } = route.query;
        const hasStatus = typeof status === 'string' && status !== '';
        const hasVehicle = typeof vehicleId === 'string' && vehicleId !== '';
        const hasSearch = typeof searchQuery === 'string' && searchQuery.trim() !== '';
        const wantsAllStatuses = status === TYRE_STATUS_FILTER_ALL;
        const pageNum = Number(pageQuery);

        if (hasSearch) {
            search.value = searchQuery.trim();
        }

        if (hasVehicle && status !== 'SCRAPPED' && !wantsAllStatuses) {
            vehicleFilter.value = vehicleId;
            statusFilter.value = 'INSTALLED';
        } else {
            // ALL = explicit All statuses; bare /tyres (no status) still defaults to In Stock.
            if (wantsAllStatuses) {
                statusFilter.value = '';
            } else if (hasStatus) {
                statusFilter.value = status;
            } else {
                // Keep search results unblocked: only default to In Stock when not searching.
                statusFilter.value = hasSearch ? '' : 'IN_STOCK';
            }
            vehicleFilter.value = '';
        }

        pagination.currentPage = Number.isFinite(pageNum) && pageNum > 0 ? Math.floor(pageNum) : 1;
    } finally {
        syncingFilters = false;
    }
}

function openCreateFromRouteQuery() {
    if (route.query.create !== '1' || !canManage.value) {
        return;
    }

    openCreate();

    const query = { ...route.query };
    delete query.create;
    router.replace({ query });
}

watch(statusFilter, () => {
    if (syncingFilters) {
        return;
    }

    clearTruckFilterIfNeeded();
});

watch(vehicleFilter, () => {
    if (syncingFilters) {
        return;
    }

    setInstalledWhenTruckSelected();
});

watch(search, (value, previous) => {
    if (syncingFilters) {
        return;
    }

    // Starting a free-text search should not stay stuck on default In Stock.
    if (value.trim() === '' || String(previous ?? '').trim() !== '') {
        return;
    }

    if (statusFilter.value === 'IN_STOCK' && !vehicleFilter.value) {
        statusFilter.value = '';
    }
});

watch(showModal, async (open) => {
    if (!open) {
        return;
    }

    await nextTick();
    serialNumberInputRef.value?.focus();
});

watch(
    () => route.query.create,
    () => {
        openCreateFromRouteQuery();
    },
);

onMounted(() => {
    applyRouteFilters();

    if (filtersActive.value) {
        filtersOpen.value = true;
    }

    restoreListFromRoute();
    openCreateFromRouteQuery();

    listAllVehicles()
        .then((rows) => {
            vehicles.value = rows;
        })
        .catch(() => {
            vehicles.value = [];
        });
});
</script>

<style scoped>
/* Custom filters layout */
@media (max-width: 991.98px) {
    :deep(.app-index-collapsible-filters__inner) {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .filter-search-col {
        flex: 0 0 260px;
        width: 260px;
    }

    .filter-truck-col {
        flex: 0 0 180px;
        width: 180px;
    }

    .filter-status-col {
        flex: 0 0 160px;
        width: 160px;
    }
}

:deep(.app-index-filters-fields) {
    display: contents;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

/* ── Tablet & Medium Screens (768px - 1199.98px) ─────────────────── */
@media (min-width: 768px) and (max-width: 1199.98px) {
    :deep(.app-index-card-toolbar) {
        gap: 6px !important;
    }

    .tyre-header-btn {
        font-size: 0.8rem !important;
        padding: 0.35rem 0.5rem !important;
        height: 32px !important;
        white-space: nowrap !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        gap: 10px !important;
    }

    .filter-search-col {
        flex: 1 1 240px !important;
        min-width: 200px !important;
    }

    .filter-truck-col {
        flex: 1 1 180px !important;
        min-width: 160px !important;
    }

    .filter-status-col {
        flex: 1 1 160px !important;
        min-width: 140px !important;
    }
}

@media (min-width: 768px) {
    .tyres-filters-content {
        display: block !important;
    }
}

/* ── Mobile Responsive Styles (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {

    /* Filters: 2 per line on mobile */
    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        width: 100% !important;
    }

    :deep(.app-index-filters-fields > div),
    :deep(.filter-search-col),
    :deep(.filter-truck-col),
    :deep(.filter-status-col),
    .filter-search-col,
    .filter-truck-col,
    .filter-status-col {
        flex: 0 0 calc(50% - 4px) !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
    }

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

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: 28px !important;
        min-height: 28px !important;
        font-size: 0.75rem !important;
        padding: 0.15rem 0.5rem !important;
        border-radius: 6px !important;
    }

    /* Table micro typography and padding on mobile */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    .table {
        font-size: 0.72rem !important;
    }

    .table th {
        font-size: 0.72rem !important;
        padding: 0.4rem 0.35rem !important;
        white-space: nowrap !important;
    }

    .table td {
        font-size: 0.72rem !important;
        padding: 0.35rem 0.35rem !important;
        vertical-align: middle !important;
    }

    .badge {
        font-size: 0.65rem !important;
        padding: 0.2rem 0.35rem !important;
    }

    :deep(.app-table-actions) {
        gap: 3px !important;
    }

    :deep(.app-crud-action-button),
    :deep(.app-index-action-btn) {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        min-height: 26px !important;
        font-size: 0.7rem !important;
        padding: 0 !important;
    }

    :deep(.app-crud-action-button i),
    :deep(.app-index-action-btn i),
    :deep(.app-index-action-btn .fas) {
        font-size: 0.6875rem !important;
    }

    /* Pagination centered in middle on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.45rem !important;
        padding: 0.6rem 0.6rem !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.75rem !important;
        text-align: center !important;
        width: 100% !important;
        margin: 0 auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: auto !important;
        margin-right: auto !important;
        display: flex !important;
        justify-content: center !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__list) {
        margin-left: auto !important;
        margin-right: auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.22rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

/* ── Extra-Small Mobile Devices (< 380px) ─────────────────────────── */
@media (max-width: 380px) {

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}

:deep(.app-modal:has(.tyre-form-modal__content)) {
    max-width: 720px;
}

.tyre-form-modal__content {
    border: 0;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
}

.tyre-form-modal__meta {
    font-size: 0.8125rem;
    color: rgba(255, 255, 255, 0.85);
}

.tyre-form-body {
    padding: 0.75rem 1rem;
    overflow-y: auto;
}

.tyre-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem 0.75rem;
    align-items: start;
}

.tyre-form-grid__full {
    grid-column: 1 / -1;
}

.tyre-form-footer {
    flex-shrink: 0;
    padding: 0.6rem 1rem;
    border-top: 1px solid #dee2e6;
    background: #fff;
    gap: 0.4rem;
}

.tyre-form-body .form-label {
    font-size: 0.8125rem;
    font-weight: 600;
    margin-bottom: 0.2rem;
}

.tyre-form-body .invalid-feedback {
    font-size: 0.75rem;
}

@media (max-width: 767.98px) {
    .tyre-form-body {
        padding: 0.5rem 0.65rem !important;
    }

    .tyre-form-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.45rem 0.5rem !important;
    }

    .tyre-form-body .form-label {
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.15rem !important;
        line-height: 1.25 !important;
    }

    .tyre-form-body .form-control,
    .tyre-form-body :deep(.form-control),
    .tyre-form-body :deep(.select-trigger) {
        height: 32px !important;
        min-height: 32px !important;
        font-size: 0.75rem !important;
        padding: 0.2rem 0.45rem !important;
    }

    .tyre-form-body :deep(.select-trigger-label) {
        font-size: 0.75rem !important;
    }

    .tyre-form-footer {
        padding: 0.45rem 0.65rem !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        gap: 5px !important;
    }

    .tyre-form-footer :deep(.btn),
    .tyre-form-footer .btn {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        font-size: 0.72rem !important;
        padding: 0.25rem 0.2rem !important;
        height: 32px !important;
        white-space: nowrap !important;
    }
}
</style>
