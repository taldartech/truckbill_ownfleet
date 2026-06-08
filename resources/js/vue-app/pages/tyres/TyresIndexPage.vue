<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Tyre Management">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> before managing tyres.
        </div>

        <AppIndexFiltersCard :active="filtersActive" @apply="loadTyres(1)" @clear="clearFilters">
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
                                <input v-model="form.purchase_date" type="date" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Cost</label>
                                <input v-model.number="form.purchase_cost" v-no-scroll-change type="number" min="0" step="0.01" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expected life (KM)</label>
                                <input v-model.number="form.expected_life_km" v-no-scroll-change type="number" min="1000" class="form-control" placeholder="40000" />
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
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-text pb-2">Date and KM limit for warranty coverage.</div>
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
                            <AppButton type="submit" label="Save" :loading="saving" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { createTyre, listTyres, updateTyre } from '../../api/tyres';
import AppButton from '../../components/ui/AppButton.vue';
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
const showModal = ref(false);
const editing = ref(null);
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const { pagination, syncFromMeta } = useListPagination(15);

const form = reactive({
    serial_number: '',
    brand: '',
    size: '',
    tyre_type: '',
    purchase_date: '',
    purchase_cost: 0,
    expected_life_km: 40000,
    warranty_valid_until: '',
    warranty_km: null,
    warranty_details: '',
    notes: '',
});

const filtersActive = computed(() => search.value.trim() !== '' || statusFilter.value !== '');

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
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
    showModal.value = true;
}

function openEdit(tyre) {
    editing.value = tyre;
    clearErrors();
    Object.assign(form, {
        serial_number: tyre.serial_number,
        brand: tyre.brand ?? '',
        size: tyre.size ?? '',
        tyre_type: tyre.tyre_type ?? '',
        purchase_date: tyre.purchase_date ?? '',
        purchase_cost: tyre.purchase_cost ?? 0,
        expected_life_km: tyre.expected_life_km ?? 40000,
        warranty_valid_until: tyre.warranty_valid_until ?? '',
        warranty_km: tyre.warranty_km ?? null,
        warranty_details: tyre.warranty_details ?? '',
        notes: tyre.notes ?? '',
    });
    showModal.value = true;
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
        });
        tyres.value = response.data;
        syncFromMeta(response.meta, tyres.value.length);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    clearErrors();

    try {
        const payload = {
            ...form,
            tyre_type: form.tyre_type || null,
            warranty_valid_until: form.warranty_valid_until || null,
            warranty_km: form.warranty_km === '' || form.warranty_km == null ? null : form.warranty_km,
        };
        if (editing.value) {
            await updateTyre(editing.value.id, payload);
        } else {
            await createTyre(payload);
        }
        closeModal();
        await loadTyres(pagination.currentPage);
    } catch (error) {
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

function formatMoney(amount) {
    return amount != null ? `₹${Number(amount).toLocaleString('en-IN')}` : '—';
}

onMounted(() => loadTyres());
</script>
