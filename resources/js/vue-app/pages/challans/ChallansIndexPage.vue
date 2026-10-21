<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard
            title="Challan Management"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading challans…"
        >
            <template #toolbar>
                <button
                    type="button"
                    class="btn btn-sm bg-white shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold challan-header-btn challan-header-btn--filter"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    <svg class="filter-slider-icon me-1" width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM9.05 3a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM2.05 8a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1H6.95a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h1.05zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-2.45 1a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05z"/>
                    </svg>
                    <span class="filter-btn-text">Filters</span>
                    <span v-if="filtersActive" class="badge bg-success text-white rounded-pill ms-1" style="font-size: 0.65rem;">Active</span>
                    <svg class="filter-chevron-icon ms-1" width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path v-if="filtersOpen" fill-rule="evenodd" d="M7.646 4.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1-.708.708L8 5.707l-5.646 5.647a.5.5 0 0 1-.708-.708l6-6z"/>
                        <path v-else fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    class="challan-header-btn challan-header-btn--create"
                    aria-label="Create challan"
                    @click="openCreate"
                >
                    <span><span class="d-none d-sm-inline">Create </span>Challan</span>
                </AppIndexHeaderButton>
            </template>

            <template #filters>
                <div v-show="filtersOpen" class="challans-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="challans-filters-panel"
                        :loading="loading"
                        @search="loadChallans(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
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
                        <div class="filter-status-col">
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
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px" class="text-center text-nowrap">#</th>
                        <th class="text-nowrap">Challan No.</th>
                        <th class="text-nowrap">Date</th>
                        <th class="text-nowrap">Truck</th>
                        <th class="text-nowrap">Driver</th>
                        <th class="text-nowrap">Violation</th>
                        <th class="text-end text-nowrap">Amount</th>
                        <th class="text-nowrap">Status</th>
                        <th v-if="hasRowActions" class="app-index-actions-cell text-end text-nowrap" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="challans.length === 0" class="app-index-empty-row">
                        <td :colspan="hasRowActions ? 9 : 8" class="text-center text-muted py-4">
                            No challans found.
                        </td>
                    </tr>
                    <tr v-for="(challan, index) in challans" :key="challan.id">
                        <td class="text-center text-nowrap">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap"><strong>{{ challan.challan_number }}</strong></td>
                        <td class="text-nowrap">{{ formatDate(challan.challan_date) }}</td>
                        <td class="text-nowrap">{{ vehicleLabel(challan) }}</td>
                        <td class="text-nowrap">{{ driverLabel(challan) }}</td>
                        <td class="text-nowrap">{{ challan.violation_type || '—' }}</td>
                        <td class="text-end text-nowrap">{{ formatAmount(challan.amount) }}</td>
                        <td class="text-nowrap">
                            <span
                                class="badge text-nowrap"
                                :class="challan.status === 'PAID' ? 'bg-success' : 'bg-warning text-dark'"
                            >
                                {{ challan.status === 'PAID' ? 'Paid' : 'Unpaid' }}
                            </span>
                        </td>
                        <td v-if="hasRowActions" class="app-index-actions-cell text-end text-nowrap">
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
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';
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
const { promptDelete } = useDeleteConfirm();
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
const filtersOpen = ref(true);
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

function handleDelete(challan) {
    promptDelete({
        title: 'Delete Challan?',
        itemName: challan.challan_number,
        details: [
            { label: 'Challan Number', value: challan.challan_number },
            { label: 'Vehicle', value: challan.vehicle?.registration_number },
            { label: 'Driver', value: challan.driver?.name },
            { label: 'Violation Type', value: challan.violation_type },
            { label: 'Amount', value: challan.amount ? `₹${challan.amount}` : null },
        ],
        onConfirm: async (remark) => {
            await deleteChallan(challan.id, { deleted_reason: remark, remark });
            toast.success('Challan deleted successfully.');

            const page =
                challans.value.length === 1 && pagination.currentPage > 1
                    ? pagination.currentPage - 1
                    : pagination.currentPage;

            await loadChallans(page);
        },
    });
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

<style scoped>
/* ── Desktop Filter Layout (>= 1200px) ────────────────────────────── */
@media (min-width: 1200px) {
    .filter-search-col {
        flex: 1 1 240px;
        min-width: 200px;
    }

    .filter-status-col {
        flex: 1 1 180px;
        min-width: 140px;
    }
}

/* ── Tablet Filter Layout (768px - 1199.98px) ──────────────────────── */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .filter-search-col {
        flex: 1 1 200px;
        min-width: 170px;
    }

    .filter-status-col {
        flex: 1 1 150px;
        min-width: 130px;
    }
}

.challan-header-btn--filter,
.challan-header-btn--filter:hover,
.challan-header-btn--filter:focus,
.challan-header-btn--filter:active {
    border-radius: 8px !important;
    border: 1px solid #ffffff !important;
    background-color: #ffffff !important;
    background: #ffffff !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
    color: var(--brand-primary, #2fb36f) !important;
    font-size: 0.8125rem !important;
    font-weight: 600 !important;
    height: 32px !important;
    padding: 0.25rem 0.75rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.35rem !important;
}

.challan-header-btn--filter .filter-btn-text,
.challan-header-btn--filter .filter-slider-icon,
.challan-header-btn--filter .filter-chevron-icon,
.challan-header-btn--filter svg,
.challan-header-btn--filter span {
    color: var(--brand-primary, #2fb36f) !important;
    fill: currentColor !important;
}

/* ── Mobile Responsive Layout (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {
    :deep(.app-index-card-toolbar) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 6px !important;
        width: 100% !important;
        margin-top: 0.35rem !important;
    }

    .challan-header-btn,
    :deep(.challan-header-btn) {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        font-size: 0.72rem !important;
        padding: 0.25rem 0.35rem !important;
        height: 30px !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .challan-header-btn i,
    :deep(.challan-header-btn i) {
        font-size: 0.75rem !important;
    }

    .challan-header-btn--create,
    :deep(.challan-header-btn--create),
    .challan-header-btn--create span,
    :deep(.challan-header-btn--create span),
    .challan-header-btn--create i,
    :deep(.challan-header-btn--create i) {
        color: var(--brand-primary, #2fb36f) !important;
        font-weight: 600 !important;
    }

    .challan-header-btn--filter,
    :deep(.challan-header-btn--filter),
    .challan-header-btn--filter:hover,
    :deep(.challan-header-btn--filter:hover),
    .challan-header-btn--filter:focus,
    :deep(.challan-header-btn--filter:focus),
    .challan-header-btn--filter:active,
    :deep(.challan-header-btn--filter:active) {
        border-radius: 8px !important;
        border: 1px solid #ffffff !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        color: var(--brand-primary, #2fb36f) !important;
        height: 30px !important;
        padding: 0.25rem 0.35rem !important;
        flex: 1 1 0 !important;
        min-width: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .challan-header-btn--filter .filter-btn-text,
    :deep(.challan-header-btn--filter .filter-btn-text),
    .challan-header-btn--filter .filter-slider-icon,
    :deep(.challan-header-btn--filter .filter-slider-icon),
    .challan-header-btn--filter .filter-chevron-icon,
    :deep(.challan-header-btn--filter .filter-chevron-icon),
    .challan-header-btn--filter svg,
    :deep(.challan-header-btn--filter svg),
    .challan-header-btn--filter span,
    :deep(.challan-header-btn--filter span) {
        color: var(--brand-primary, #2fb36f) !important;
        fill: currentColor !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        padding: 0.5rem 0.65rem !important;
    }

    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    /* 2 filters per line on mobile */
    .filter-search-col,
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

    /* Search and Reset buttons in 1 single row on mobile */
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
    .challan-header-btn {
        font-size: 0.65rem !important;
        padding: 0.25rem 0.15rem !important;
        height: 28px !important;
    }

    .challan-header-btn i,
    .challan-header-btn :deep(i) {
        font-size: 0.68rem !important;
        margin-right: 1px !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>

