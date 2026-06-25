<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader
            title="Tyre Management"
            :subtitle="selectedTruckLabel ? `Truck: ${selectedTruckLabel}` : ''"
        >
            <template #actions>
                <AppButton
                    v-if="vehicleFilter && truckFilterApplicable"
                    variant="secondary"
                    outline
                    size="sm"
                    icon="bi bi-truck"
                    label="Change truck"
                    @click="openTruckPicker"
                />
                <RouterLink
                    v-if="vehicleFilter && truckFilterApplicable"
                    :to="{ name: 'vehicles.tyres', params: { vehicleId: vehicleFilter } }"
                    class="btn btn-outline-success btn-sm"
                >
                    <i class="bi bi-grid-3x3-gap"></i> Positions
                </RouterLink>
                <AppButton v-if="canManage" icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing tyres.
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <template v-if="truckContextReady">
        <AppIndexFiltersCard :active="filtersActive" @apply="applyFilters" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="search"
                    input-id="tyresIndex_search"
                    placeholder="Tyre number, brand, size…"
                    max-width="100%"
                    :disabled="loading"
                />
            </div>
            <div v-if="truckFilterApplicable">
                <label class="form-label" for="tyresIndex_vehicle">Truck</label>
                <SearchableSelect
                    input-id="tyresIndex_vehicle"
                    v-model="vehicleFilter"
                    :items="vehicleSelectOptions"
                    placeholder="All trucks"
                    search-placeholder="Search truck number…"
                    empty-message="No truck found."
                    :disabled="loading || Boolean(vehicleFilter)"
                />
                <div v-if="vehicleFilter" class="form-text">
                    Use <strong>Change truck</strong> above to pick a different truck.
                </div>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="statusFilter" class="form-select" :disabled="loading">
                    <option value="">All statuses</option>
                    <option v-for="s in tyreStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading tyres…">
            <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tyre #</th>
                            <th>Brand</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Warranty</th>
                            <th>Truck / Position</th>
                            <th>Purchase</th>
                            <th v-if="canManage" class="app-index-actions-cell" style="width: 140px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="tyres.length === 0" class="app-index-empty-row">
                            <td :colspan="canManage ? 9 : 8" class="text-center text-muted py-4">No tyres found.</td>
                        </tr>
                        <tr v-for="tyre in tyres" :key="tyre.id">
                            <td>
                                <RouterLink
                                    :to="{ name: 'tyres.show', params: { id: tyre.id } }"
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
                <AppIndexPaginationFooter
                    page-id="tyresIndex"
                    :loading="loading"
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :per-page="pagination.perPage"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                    item-label="tyres"
                    :per-page-options="[15, 25, 50]"
                    @page-change="loadTyres"
                    @update:per-page="onPerPageUpdate"
                />
            </template>
        </AppIndexListCard>
        </template>

        <div
            v-if="showTruckPicker"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
        >
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-truck me-1"></i>
                            Select Truck
                        </h5>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Choose a truck to view tyres installed on it, or open the full inventory list.
                        </p>
                        <label class="form-label" for="tyresTruckPicker_vehicle">Truck</label>
                        <SearchableSelect
                            input-id="tyresTruckPicker_vehicle"
                            v-model="pickerVehicleId"
                            :items="vehicleSelectOptions"
                            placeholder="Search and select truck…"
                            search-placeholder="Search truck number…"
                            empty-message="No truck found."
                            :disabled="vehiclesLoading"
                        />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" @click="viewAllTrucks">
                            All tyres (inventory)
                        </button>
                        <AppButton
                            label="View truck tyres"
                            icon="bi bi-arrow-right"
                            :disabled="!pickerVehicleId"
                            @click="confirmTruckSelection"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0, 0, 0, 0.45)"
            @click.self="closeModal"
        >
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">{{ editing ? 'Edit Tyre' : 'Add Tyre' }}</h5>
                        <button type="button" class="btn-close btn-close-white" @click="closeModal"></button>
                    </div>
                    <form novalidate @submit.prevent="save">
                        <div class="modal-body row g-3">
                            <div v-if="formError" class="col-12">
                                <div class="alert alert-danger py-2 mb-0">{{ formError }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tyre Number *</label>
                                <input
                                    ref="serialNumberInputRef"
                                    v-model="form.serial_number"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.serial_number }"
                                    required
                                />
                                <div v-if="fieldErrors.serial_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.serial_number[0] }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input
                                    v-model="form.brand"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.brand }"
                                />
                                <div v-if="fieldErrors.brand" class="invalid-feedback d-block">
                                    {{ fieldErrors.brand[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Size</label>
                                <input
                                    v-model="form.size"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.size }"
                                    placeholder="10.00-20"
                                />
                                <div v-if="fieldErrors.size" class="invalid-feedback d-block">
                                    {{ fieldErrors.size[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tyre Type</label>
                                <select
                                    v-model="form.tyre_type"
                                    class="form-select"
                                    :class="{ 'is-invalid': fieldErrors.tyre_type }"
                                >
                                    <option value="">—</option>
                                    <option v-for="t in tyreTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                                <div v-if="fieldErrors.tyre_type" class="invalid-feedback d-block">
                                    {{ fieldErrors.tyre_type[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Date</label>
                                <input
                                    v-model="form.purchase_date"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.purchase_date }"
                                />
                                <div v-if="fieldErrors.purchase_date" class="invalid-feedback d-block">
                                    {{ fieldErrors.purchase_date[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Cost</label>
                                <input
                                    v-model.number="form.purchase_cost"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.purchase_cost }"
                                />
                                <div v-if="fieldErrors.purchase_cost" class="invalid-feedback d-block">
                                    {{ fieldErrors.purchase_cost[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expected life (KM)</label>
                                <input v-model.number="form.expected_life_km" v-no-scroll-change type="number" min="1000" class="form-control" placeholder="40000" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">KM already used</label>
                                <input
                                    v-model.number="form.current_km"
                                    v-no-scroll-change
                                    type="number"
                                    min="0"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.current_km }"
                                    placeholder="0"
                                />
                                <div v-if="fieldErrors.current_km" class="invalid-feedback d-block">
                                    {{ fieldErrors.current_km[0] }}
                                </div>
                                <div v-else class="form-text">
                                    0 = new tyre. Prior KM before this fleet, or fix a wrong entry at create time.
                                    <span v-if="editing && minimumRecordedKm > 0">
                                        Minimum {{ minimumRecordedKm }} km (already run on trucks).
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Warranty Valid Until</label>
                                <input
                                    v-model="form.warranty_valid_until"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.warranty_valid_until }"
                                />
                                <div v-if="fieldErrors.warranty_valid_until" class="invalid-feedback d-block">
                                    {{ fieldErrors.warranty_valid_until[0] }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Warranty KM Limit</label>
                                <input
                                    v-model.number="form.warranty_km"
                                    v-no-scroll-change type="number"
                                    min="0"
                                    step="1"
                                    class="form-control"
                                    placeholder="e.g. 40000"
                                    :class="{ 'is-invalid': fieldErrors.warranty_km }"
                                />
                                <div v-if="fieldErrors.warranty_km" class="invalid-feedback d-block">
                                    {{ fieldErrors.warranty_km[0] }}
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Warranty Notes</label>
                                <textarea
                                    v-model="form.warranty_details"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Dealer name, claim terms, etc."
                                />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea v-model="form.notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                            <AppButton
                                v-if="!editing"
                                type="button"
                                variant="primary"
                                outline
                                label="Save & Add More"
                                :loading="saving"
                                @click="saveAndNext"
                            />
                            <AppButton type="submit" label="Save" :loading="saving" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { listAllVehicles } from '../../api/vehicles';
import { createTyre, getTyre, listTyres, updateTyre } from '../../api/tyres';
import AppButton from '../../components/ui/AppButton.vue';
import SearchableSelect from '../../components/ui/SearchableSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import {
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

const route = useRoute();
const router = useRouter();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));

const tyreStatuses = TYRE_STATUSES;
const tyreTypes = TYRE_TYPES;

const tyres = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const statusFilter = ref('');
const vehicleFilter = ref('');
const truckFilterApplicable = computed(
    () => statusFilter.value !== 'IN_STOCK' && statusFilter.value !== 'SCRAPPED',
);
const vehicles = ref([]);
const vehiclesLoading = ref(true);
const vehicleSelectOptions = computed(() =>
    vehicles.value.map((vehicle) => ({
        id: vehicle.id,
        label: vehicle.registration_number,
    })),
);
const showTruckPicker = ref(false);
const truckContextReady = ref(false);
const pickerVehicleId = ref('');
const selectedTruckLabel = computed(() => {
    if (!vehicleFilter.value) {
        return '';
    }

    const match = vehicles.value.find((vehicle) => String(vehicle.id) === String(vehicleFilter.value));
    return match?.registration_number ?? '';
});
const showModal = ref(false);
const editing = ref(null);
const serialNumberInputRef = ref(null);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const { pagination, syncFromMeta } = useListPagination(15);
const flash = reactive({ success: '', error: '' });

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

const form = reactive({
    serial_number: '',
    brand: '',
    size: '',
    tyre_type: '',
    purchase_date: '',
    purchase_cost: 0,
    expected_life_km: 40000,
    current_km: 0,
    warranty_valid_until: '',
    warranty_km: null,
    warranty_details: '',
    notes: '',
});

const filtersActive = computed(
    () => search.value.trim() !== '' || statusFilter.value !== '' || vehicleFilter.value !== '',
);

const minimumRecordedKm = computed(() => Number(editing.value?.minimum_recorded_km ?? 0));

function clearTruckFilterIfNeeded() {
    if (truckFilterApplicable.value) {
        return;
    }

    if (vehicleFilter.value) {
        vehicleFilter.value = '';
        syncVehicleQuery();
    }

    truckContextReady.value = true;
}

function applyFilters() {
    clearTruckFilterIfNeeded();
    syncVehicleQuery();
    loadTyres(1);
}

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
    if (!vehicleFilter.value) {
        loadTyres(1);
        return;
    }

    vehicleFilter.value = '';
    syncVehicleQuery();
    openTruckPicker();
}

function syncVehicleQuery() {
    const query = { ...route.query };

    if (vehicleFilter.value) {
        query.vehicle_id = String(vehicleFilter.value);
    } else {
        delete query.vehicle_id;
    }

    router.replace({ query });
}

function openTruckPicker() {
    pickerVehicleId.value = vehicleFilter.value;
    truckContextReady.value = false;
    showTruckPicker.value = true;
}

function confirmTruckSelection() {
    if (!pickerVehicleId.value) {
        return;
    }

    vehicleFilter.value = String(pickerVehicleId.value);
    statusFilter.value = '';
    showTruckPicker.value = false;
    truckContextReady.value = true;
    syncVehicleQuery();
    loadTyres(1);
}

function viewAllTrucks() {
    vehicleFilter.value = '';
    showTruckPicker.value = false;
    truckContextReady.value = true;
    syncVehicleQuery();
    loadTyres(1);
}

function resetForm() {
    Object.assign(form, {
        serial_number: '',
        brand: '',
        size: '',
        tyre_type: '',
        purchase_date: '',
        purchase_cost: 0,
        expected_life_km: 40000,
        current_km: 0,
        warranty_valid_until: '',
        warranty_km: null,
        warranty_details: '',
        notes: '',
    });
}

function openCreate() {
    editing.value = null;
    resetForm();
    clearErrors();
    flash.success = '';
    flash.error = '';
    showModal.value = true;
}

async function openEdit(tyre) {
    editing.value = tyre;
    clearErrors();
    flash.success = '';
    flash.error = '';
    Object.assign(form, {
        serial_number: tyre.serial_number,
        brand: tyre.brand ?? '',
        size: tyre.size ?? '',
        tyre_type: tyre.tyre_type ?? '',
        purchase_date: tyre.purchase_date ?? '',
        purchase_cost: tyre.purchase_cost ?? 0,
        expected_life_km: tyre.expected_life_km ?? 40000,
        current_km: tyre.current_km ?? 0,
        warranty_valid_until: tyre.warranty_valid_until ?? '',
        warranty_km: tyre.warranty_km ?? null,
        warranty_details: tyre.warranty_details ?? '',
        notes: tyre.notes ?? '',
    });
    showModal.value = true;

    try {
        const payload = await getTyre(tyre.id);
        editing.value = {
            ...tyre,
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
    router.push({ name: 'tyres.show', params: { id } });
}

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    loadTyres(1);
}

async function loadTyres(page = 1) {
    if (needsCompanySelection.value) {
        loading.value = false;
        return;
    }
    loading.value = true;
    try {
        const response = await listTyres({
            page,
            perPage: pagination.perPage,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            vehicleId: truckFilterApplicable.value && vehicleFilter.value ? vehicleFilter.value : undefined,
        });
        tyres.value = response.data;
        syncFromMeta(response.meta, tyres.value.length);
    } finally {
        loading.value = false;
    }
}

function buildPayload() {
    return {
        ...form,
        tyre_type: form.tyre_type || null,
        purchase_date: form.purchase_date || null,
        purchase_cost: form.purchase_cost === '' || form.purchase_cost == null ? null : form.purchase_cost,
        warranty_valid_until: form.warranty_valid_until || null,
        warranty_km: form.warranty_km === '' || form.warranty_km == null ? null : form.warranty_km,
    };
}

async function persistTyre() {
    const payload = buildPayload();

    if (editing.value) {
        await updateTyre(editing.value.id, payload);
        return;
    }

    await createTyre(payload);
}

async function save() {
    saving.value = true;
    clearErrors();

    try {
        await persistTyre();
        showToast(editing.value ? 'Tyre updated successfully.' : 'Tyre added successfully.');
        closeModal();
        await loadTyres(pagination.currentPage);
    } catch (error) {
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function saveAndNext() {
    if (editing.value) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await persistTyre();
        showToast('Tyre added successfully.');
        await loadTyres(pagination.currentPage);
        form.serial_number = '';
        await nextTick();
        serialNumberInputRef.value?.focus();
    } catch (error) {
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

function formatMoney(amount) {
    return amount != null ? `₹${Number(amount).toLocaleString('en-IN')}` : '—';
}

function applyRouteFilters() {
    const { status, vehicle_id: vehicleId } = route.query;

    if (typeof status === 'string' && status !== '') {
        statusFilter.value = status;
    }

    const skipTruckFilter = statusFilter.value === 'IN_STOCK' || statusFilter.value === 'SCRAPPED';

    if (!skipTruckFilter && typeof vehicleId === 'string' && vehicleId !== '') {
        vehicleFilter.value = vehicleId;
        pickerVehicleId.value = vehicleId;
        truckContextReady.value = true;
    }
}

watch(statusFilter, () => {
    clearTruckFilterIfNeeded();
});

onMounted(async () => {
    applyRouteFilters();
    vehiclesLoading.value = true;

    try {
        vehicles.value = await listAllVehicles();
    } finally {
        vehiclesLoading.value = false;
    }

    if (truckContextReady.value) {
        await loadTyres();
        return;
    }

    if (statusFilter.value === 'IN_STOCK' || statusFilter.value === 'SCRAPPED') {
        truckContextReady.value = true;
        await loadTyres();
        return;
    }

    loading.value = false;
    showTruckPicker.value = !needsCompanySelection.value;
});
</script>
