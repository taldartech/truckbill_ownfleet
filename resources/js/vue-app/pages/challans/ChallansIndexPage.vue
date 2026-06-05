<template>
    <div class="container mt-4 challans-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Challan Management</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" label="Add Challan" @click="openCreate" />
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">Challan List</h3>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select
                        v-model="statusFilter"
                        class="form-select form-select-sm"
                        style="width: auto"
                        :disabled="loading"
                        @change="onFilterChange"
                    >
                        <option value="">All statuses</option>
                        <option value="UNPAID">Unpaid</option>
                        <option value="PAID">Paid</option>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 280px">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="form-control"
                            placeholder="Search challan, vehicle, driver…"
                            aria-label="Search challans"
                        />
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading challans…</div>
                <table v-else class="table table-bordered table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width: 70px">#</th>
                            <th>Challan No.</th>
                            <th>Date</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Violation</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th v-if="hasRowActions" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="challans.length === 0">
                            <td :colspan="hasRowActions ? 9 : 8" class="text-center text-muted py-4">
                                No challans found.
                            </td>
                        </tr>
                        <tr v-for="(challan, index) in challans" :key="challan.id">
                            <td class="text-center">{{ rowNumber(index) }}</td>
                            <td><strong>{{ challan.challan_number }}</strong></td>
                            <td>{{ formatDate(challan.challan_date) }}</td>
                            <td>{{ vehicleLabel(challan) }}</td>
                            <td>{{ driverLabel(challan) }}</td>
                            <td>{{ challan.violation_type || '—' }}</td>
                            <td class="text-end">{{ formatAmount(challan.amount) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="challan.status === 'PAID' ? 'bg-success' : 'bg-warning text-dark'"
                                >
                                    {{ challan.status === 'PAID' ? 'Paid' : 'Unpaid' }}
                                </span>
                            </td>
                            <td v-if="hasRowActions">
                                <AppTableActions>
                                    <AppCrudActionButton v-if="canManage" action="edit" @click="openEdit(challan)" />
                                    <AppCrudActionButton v-if="canDelete" action="delete" @click="handleDelete(challan)" />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="pagination.total > 0"
                class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2"
            >
                <small class="text-muted">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} challans
                </small>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0" :for="`${pageId}_per_page`">Per page</label>
                    <select
                        :id="`${pageId}_per_page`"
                        v-model.number="pagination.perPage"
                        class="form-select form-select-sm"
                        style="width: auto"
                        :disabled="loading"
                        @change="onPerPageChange"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <div v-if="pagination.lastPage > 1" class="btn-group btn-group-sm">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="loading || pagination.currentPage <= 1"
                            @click="goToPage(pagination.currentPage - 1)"
                        >
                            Previous
                        </button>
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="loading || pagination.currentPage >= pagination.lastPage"
                            @click="goToPage(pagination.currentPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ChallanFormModal
            modal-id="addChallanModal"
            :show="showCreate"
            mode="create"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="challanFormError"
            @close="closeCreate"
            @submit="handleCreate"
        />

        <ChallanFormModal
            modal-id="editChallanModal"
            :show="showEdit"
            mode="edit"
            :challan="editingChallan"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="challanFormError"
            @close="closeEdit"
            @submit="handleUpdate"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { createChallan, deleteChallan, listChallans, updateChallan } from '../../api/challans';
import ChallanFormModal from '../../components/challans/ChallanFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { friendlyApiErrorMessage } from '../../utils/formErrors';

const pageId = 'challansIndex';

const toast = useAppToast();
const { fieldErrors, formError: challanFormError, clearErrors, applyApiError } = useApiFormErrors();
const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('challans.manage'));
const canDelete = computed(() => canInCompany('challans.delete'));
const hasRowActions = computed(() => canManage.value || canDelete.value);

const challans = ref([]);
const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const showEdit = ref(false);
const editingChallan = ref(null);
const searchQuery = ref('');
const statusFilter = ref('');
const pagination = reactive({
    currentPage: 1,
    perPage: 25,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0,
});

let searchDebounceTimer = null;

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const [year, month, day] = String(value).split('-');

    if (!year || !month || !day) {
        return value;
    }

    return `${day}/${month}/${year}`;
}

function formatAmount(amount) {
    const value = Number(amount ?? 0);

    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(value);
}

function vehicleLabel(challan) {
    return challan.vehicle?.registration_number ?? '—';
}

function driverLabel(challan) {
    return challan.driver?.name ?? '—';
}

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

async function loadChallans(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listChallans({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
            status: statusFilter.value,
        });

        challans.value = response.data;
        pagination.currentPage = response.meta.current_page ?? page;
        pagination.lastPage = response.meta.last_page ?? 1;
        pagination.total = response.meta.total ?? challans.value.length;
        pagination.from = response.meta.from ?? (challans.value.length > 0 ? 1 : 0);
        pagination.to = response.meta.to ?? challans.value.length;
    } catch {
        toast.error('Could not load challans.');
        challans.value = [];
        pagination.currentPage = 1;
        pagination.lastPage = 1;
        pagination.total = 0;
        pagination.from = 0;
        pagination.to = 0;
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadChallans(page);
}

function onPerPageChange() {
    pagination.currentPage = 1;
    loadChallans(1);
}

function onFilterChange() {
    pagination.currentPage = 1;
    loadChallans(1);
}

function openCreate() {
    clearErrors();
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    clearErrors();
}

function openEdit(challan) {
    editingChallan.value = { ...challan };
    clearErrors();
    showEdit.value = true;
}

function closeEdit() {
    showEdit.value = false;
    editingChallan.value = null;
    clearErrors();
}

function applyValidationError(error, fallback = 'Unable to save challan. Please try again.') {
    if (applyApiError(error, fallback)) {
        toast.error(challanFormError.value || fallback);

        return;
    }

    challanFormError.value = friendlyApiErrorMessage(error, fallback);
    toast.error(challanFormError.value);
}

async function handleCreate(payload) {
    saving.value = true;
    clearErrors();

    try {
        await createChallan(payload);
        toast.success('Challan has been added successfully.');
        closeCreate();
        await loadChallans(1);
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleUpdate(payload) {
    if (!editingChallan.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await updateChallan(editingChallan.value.id, payload);
        toast.success('Challan has been updated successfully.');
        closeEdit();
        await loadChallans();
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleDelete(challan) {
    if (!window.confirm(`Delete challan "${challan.challan_number}"?`)) {
        return;
    }

    try {
        await deleteChallan(challan.id);
        toast.success('Challan deleted successfully.');

        const page =
            challans.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        await loadChallans(page);
    } catch (error) {
        toast.error(friendlyApiErrorMessage(error, 'Unable to delete challan. Please try again.'));
    }
}

watch(searchQuery, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        pagination.currentPage = 1;
        loadChallans(1);
    }, 400);
});

onMounted(() => {
    loadChallans();
});
</script>
