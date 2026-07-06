<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Challan Management">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <AppIndexFiltersCard :active="filtersActive" @apply="applyFilters" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="searchQuery"
                    :input-id="`${pageId}_search`"
                    placeholder="Search challan, truck, driver…"
                    max-width="100%"
                    :disabled="loading"
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
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading challans…">
            <table class="table table-hover align-middle mb-0">
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
                        <th v-if="hasRowActions" class="app-index-actions-cell" style="width: 120px">Actions</th>
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
                        <td v-if="hasRowActions" class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton v-if="canManage" action="edit" @click="openEdit(challan)" />
                                <AppCrudActionButton v-if="canDelete" action="delete" @click="handleDelete(challan)" />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer>
                <AppIndexPaginationFooter
                    :page-id="pageId"
                    :loading="loading"
                    :current-page="pagination.currentPage"
                    :last-page="pagination.lastPage"
                    :per-page="pagination.perPage"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                    item-label="challans"
                    @page-change="goToPage"
                    @update:per-page="onPerPageUpdate"
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
import { computed, onMounted, reactive, ref } from 'vue';
import { createChallan, deleteChallan, listChallans, updateChallan } from '../../api/challans';
import ChallanFormModal from '../../components/challans/ChallanFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexFiltersCard from '../../components/ui/AppIndexFiltersCard.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { friendlyApiErrorMessage } from '../../utils/formErrors';
import { formatDate } from '../../utils/datetime';

const pageId = 'challansIndex';

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

const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    perPage: 25,
    total: 0,
    from: 0,
    to: 0,
});

const filtersActive = computed(() => searchQuery.value.trim() !== '' || statusFilter.value !== '');

function applyFilters() {
    pagination.currentPage = 1;
    loadChallans(1);
}

function clearFilters() {
    searchQuery.value = '';
    statusFilter.value = '';
    applyFilters();
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

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
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
});
</script>
