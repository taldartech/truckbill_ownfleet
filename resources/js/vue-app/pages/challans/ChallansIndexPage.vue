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
                    v-if="canManage"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Create Challan"
                    aria-label="Create challan"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Create </span>Challan</span>
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
@media (min-width: 768px) {
    .challans-filters-content {
        display: block !important;
    }
}

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

/* ── Mobile Responsive Layout (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {

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

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>

