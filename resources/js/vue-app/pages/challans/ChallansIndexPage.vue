<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Challan Management" :loading="loading" loading-text="Loading challans…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="challans-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    aria-label="Create challan"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="challans-filters-panel"
                :loading="loading"
                @search="loadChallans(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="challansIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="searchQuery"
                        input-id="challansIndex_search"
                        placeholder="Search challan, truck, driver…"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadChallans(1)"
                    />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <AppSelect
                        v-model="statusFilter"
                        :options="challanStatusOptions"
                        placeholder="All statuses"
                        search-placeholder="Search status…"
                        :disabled="loading"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Challan No.</th>
                        <th>Date</th>
                        <th>Truck</th>
                        <th>Driver</th>
                        <th>Violation</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th v-if="hasRowActions" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="challans.length === 0" class="app-index-empty-row">
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
                        <td v-if="hasRowActions" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canManage"
                                    action="edit"
                                    title="Edit"
                                    @click="openEdit(challan)"
                                />
                                <AppCrudActionButton
                                    v-if="canDelete"
                                    action="delete"
                                    title="Delete"
                                    @click="handleDelete(challan)"
                                />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="challans"
                    aria-label="Challans pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

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
import { computed, onMounted, ref } from 'vue';
import { createChallan, deleteChallan, listChallans, updateChallan } from '../../api/challans';
import ChallanFormModal from '../../components/challans/ChallanFormModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatDate } from '../../utils/datetime';

const challanStatusOptions = [
    { value: 'UNPAID', label: 'Unpaid' },
    { value: 'PAID', label: 'Paid' },
];

const toast = useAppToast();
const { fieldErrors, formError: challanFormError, clearErrors, applyApiError } = useApiFormErrors();
const { canInCompany } = usePermissions();
const canView = computed(() => canInCompany('challans.view'));
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
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersActive = computed(() => searchQuery.value.trim() !== '' || statusFilter.value !== '');

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    searchQuery.value = '';
    statusFilter.value = '';
    pagination.currentPage = 1;
    loadChallans(1);
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
    if (!canView.value) {
        challans.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        const response = await listChallans({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
            status: statusFilter.value,
        });

        challans.value = response.data;
        syncFromMeta(response.meta, challans.value.length);
    } catch {
        toast.error('Could not load challans.');
        challans.value = [];
        resetPagination();
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

    toast.error(friendlyApiErrorMessage(error, fallback));
}

async function handleCreate(payload) {
    saving.value = true;
    clearErrors();

    try {
        await createChallan(payload);
        toast.success('Challan added successfully.');
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
        toast.success('Challan updated successfully.');
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

onMounted(() => {
    if (canView.value) {
        loadChallans();
    } else {
        loading.value = false;
    }

    if (filtersActive.value) {
        filtersOpen.value = true;
    }
});
</script>
