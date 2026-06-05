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

        <AppIndexFiltersCard :show-actions="false">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="search"
                    input-id="tyresIndex_search"
                    placeholder="Tyre number, brand, size"
                    max-width="100%"
                    :disabled="loading"
                    @update:model-value="debouncedLoad"
                />
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select v-model="statusFilter" class="form-select" @change="loadTyres(1)">
                    <option value="">All</option>
                    <option v-for="s in tyreStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <div class="card app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">Tyre Master</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading…</div>
                <table v-else class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tyre #</th>
                            <th>Brand</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Vehicle / Position</th>
                            <th>Purchase</th>
                            <th v-if="canManage" style="width: 140px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="tyres.length === 0">
                            <td :colspan="canManage ? 8 : 7" class="text-center text-muted">No tyres found.</td>
                        </tr>
                        <tr v-for="tyre in tyres" :key="tyre.id">
                            <td>
                                <RouterLink :to="{ name: 'tyres.show', params: { id: tyre.id } }">
                                    {{ tyre.serial_number }}
                                </RouterLink>
                            </td>
                            <td>{{ tyre.brand || '—' }}</td>
                            <td>{{ tyre.size || '—' }}</td>
                            <td>{{ formatType(tyre.tyre_type) }}</td>
                            <td><span class="badge bg-secondary">{{ formatStatus(tyre.status) }}</span></td>
                            <td>
                                <template v-if="tyre.active_installation">
                                    {{ tyre.active_installation.vehicle_registration }}
                                    ({{ tyre.active_installation.position_label }})
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>{{ formatMoney(tyre.purchase_cost) }}</td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="view" @click="goToDetail(tyre.id)" />
                                    <AppCrudActionButton action="edit" @click="openEdit(tyre)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                    <form @submit.prevent="save">
                        <div class="modal-body row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tyre Number *</label>
                                <input v-model="form.serial_number" type="text" class="form-control" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input v-model="form.brand" type="text" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Size</label>
                                <input v-model="form.size" type="text" class="form-control" placeholder="10.00-20" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tyre Type</label>
                                <select v-model="form.tyre_type" class="form-select">
                                    <option value="">—</option>
                                    <option v-for="t in tyreTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Date</label>
                                <input v-model="form.purchase_date" type="date" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Cost</label>
                                <input v-model.number="form.purchase_cost" type="number" min="0" step="0.01" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expected life (KM)</label>
                                <input v-model.number="form.expected_life_km" type="number" min="1000" class="form-control" placeholder="40000" />
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Warranty Details</label>
                                <input v-model="form.warranty_details" type="text" class="form-control" />
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
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';

const router = useRouter();
const { needsCompanySelection } = useRequireCompanyContext();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('tyres.manage'));

const tyreStatuses = [
    { value: 'IN_STOCK', label: 'In Stock' },
    { value: 'INSTALLED', label: 'Installed' },
    { value: 'RETREADED', label: 'Retreaded' },
    { value: 'DAMAGED', label: 'Damaged' },
    { value: 'SCRAPPED', label: 'Scrapped' },
];

const tyreTypes = [
    { value: 'RADIAL', label: 'Radial' },
    { value: 'BIAS', label: 'Bias' },
    { value: 'TUBELESS', label: 'Tubeless' },
    { value: 'TUBED', label: 'Tubed' },
    { value: 'OTHER', label: 'Other' },
];

const tyres = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const statusFilter = ref('');
const showModal = ref(false);
const editing = ref(null);
const { pagination, syncFromMeta } = useListPagination(15);

const form = reactive({
    serial_number: '',
    brand: '',
    size: '',
    tyre_type: '',
    purchase_date: '',
    purchase_cost: 0,
    expected_life_km: 40000,
    warranty_details: '',
    notes: '',
});

let debounceTimer = null;

function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadTyres(1), 350);
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
        warranty_details: '',
        notes: '',
    });
}

function openCreate() {
    editing.value = null;
    resetForm();
    showModal.value = true;
}

function openEdit(tyre) {
    editing.value = tyre;
    Object.assign(form, {
        serial_number: tyre.serial_number,
        brand: tyre.brand ?? '',
        size: tyre.size ?? '',
        tyre_type: tyre.tyre_type ?? '',
        purchase_date: tyre.purchase_date ?? '',
        purchase_cost: tyre.purchase_cost ?? 0,
        expected_life_km: tyre.expected_life_km ?? 40000,
        warranty_details: tyre.warranty_details ?? '',
        notes: tyre.notes ?? '',
    });
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
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
    try {
        const payload = { ...form, tyre_type: form.tyre_type || null };
        if (editing.value) {
            await updateTyre(editing.value.id, payload);
        } else {
            await createTyre(payload);
        }
        closeModal();
        await loadTyres(pagination.currentPage);
    } finally {
        saving.value = false;
    }
}

function formatStatus(status) {
    return tyreStatuses.find((s) => s.value === status)?.label ?? status ?? '—';
}

function formatType(type) {
    return tyreTypes.find((t) => t.value === type)?.label ?? type ?? '—';
}

function formatMoney(amount) {
    return amount != null ? `₹${Number(amount).toLocaleString('en-IN')}` : '—';
}

onMounted(() => loadTyres());
</script>
