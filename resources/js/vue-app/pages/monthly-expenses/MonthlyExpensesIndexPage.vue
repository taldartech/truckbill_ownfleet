<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Monthly Expenses" header-class="trips-card-header--gradient" :loading="loading" loading-text="Loading monthly expenses…">
            <template #toolbar>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Create Monthly Expense"
                    aria-label="Add monthly expense"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1">Create</span>
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

                <div v-show="filtersOpen" class="monthly-expenses-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="monthly-expenses-filters-panel"
                        :loading="loading"
                        @search="loadExpenses(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label" for="monthlyExpenses_search">Search</label>
                            <AppIndexSearchInput
                                v-model="searchQuery"
                                input-id="monthlyExpenses_search"
                                placeholder="Search particulars…"
                                max-width="100%"
                                :disabled="loading"
                                @keydown.enter="loadExpenses(1)"
                            />
                        </div>
                        <div class="filter-month-col">
                            <label class="form-label" for="monthlyExpenses_month">Month</label>
                            <input
                                id="monthlyExpenses_month"
                                v-model="monthFilter"
                                type="month"
                                class="form-control"
                                :disabled="loading"
                            />
                        </div>
                    </AppIndexCollapsibleFilters>
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px" class="text-center text-nowrap">#</th>
                        <th class="text-nowrap">Month</th>
                        <th class="text-nowrap">Particulars</th>
                        <th class="text-end text-nowrap">Amount</th>
                        <th v-if="canManage" class="app-index-actions-cell text-end text-nowrap" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="expenses.length === 0" class="app-index-empty-row">
                        <td :colspan="canManage ? 5 : 4" class="text-center text-muted py-4">
                            No monthly expenses found.
                        </td>
                    </tr>
                    <tr v-for="(expense, index) in expenses" :key="expense.id">
                        <td class="text-center text-nowrap">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">{{ formatMonth(expense.month) }}</td>
                        <td class="text-nowrap">{{ expense.particulars }}</td>
                        <td class="text-end text-nowrap">{{ formatAmount(expense.amount) }}</td>
                        <td v-if="canManage" class="app-index-actions-cell text-end text-nowrap">
                            <AppTableActions>
                                <AppCrudActionButton action="edit" title="Edit" @click="openEdit(expense)" />
                                <AppCrudActionButton action="delete" title="Delete" @click="handleDelete(expense)" />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="expenses"
                    aria-label="Monthly expenses pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <AppModal
            :show="showModal"
            :loading="saving"
            :form-error="formError"
            as-form
            @close="closeModal"
            @submit="handleSubmit"
        >
            <template #title>
                <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-plus-circle'"></i>
                {{ isEdit ? 'Edit Monthly Expense' : 'Add Monthly Expense' }}
            </template>

            <div class="mb-3">
                <label for="monthlyExpense_month" class="form-label">Month *</label>
                <input
                    id="monthlyExpense_month"
                    v-model="form.month"
                    type="month"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.month }"
                    required
                />
                <div v-if="fieldErrors.month" class="invalid-feedback d-block">
                    {{ fieldErrors.month[0] }}
                </div>
            </div>

            <div class="mb-3">
                <label for="monthlyExpense_particulars" class="form-label">Particulars *</label>
                <input
                    id="monthlyExpense_particulars"
                    v-model="form.particulars"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.particulars }"
                    maxlength="255"
                    required
                />
                <div v-if="fieldErrors.particulars" class="invalid-feedback d-block">
                    {{ fieldErrors.particulars[0] }}
                </div>
            </div>

            <div class="mb-3">
                <label for="monthlyExpense_amount" class="form-label">Amount *</label>
                <input
                    id="monthlyExpense_amount"
                    v-model="form.amount"
                    type="number"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.amount }"
                    min="0.01"
                    step="0.01"
                    required
                />
                <div v-if="fieldErrors.amount" class="invalid-feedback d-block">
                    {{ fieldErrors.amount[0] }}
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="closeModal">
                    Cancel
                </button>
                <button type="submit" class="btn btn-brand-primary" :disabled="saving">
                    {{ saving ? 'Saving…' : isEdit ? 'Update' : 'Save' }}
                </button>
            </template>
        </AppModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    createMonthlyExpense,
    deleteMonthlyExpense,
    listMonthlyExpenses,
    updateMonthlyExpense,
} from '../../api/monthlyExpenses';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { friendlyApiErrorMessage } from '../../utils/formErrors';

const toast = useAppToast();
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const { canInCompany } = usePermissions();
const canView = computed(() => canInCompany('monthly_expenses.view'));
const canManage = computed(() => canInCompany('monthly_expenses.manage'));

const expenses = ref([]);
const loading = ref(true);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);
const searchQuery = ref('');
const monthFilter = ref('');
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filtersActive = computed(() => searchQuery.value.trim() !== '' || monthFilter.value !== '');

const form = reactive({
    month: '',
    particulars: '',
    amount: '',
});

const isEdit = computed(() => editingId.value !== null);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    searchQuery.value = '';
    monthFilter.value = '';
    pagination.currentPage = 1;
    loadExpenses(1);
}

function formatAmount(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
}

function formatMonth(month) {
    if (!month || !/^\d{4}-\d{2}$/.test(month)) {
        return month || '—';
    }

    const [year, monthPart] = month.split('-');
    const date = new Date(Number(year), Number(monthPart) - 1, 1);

    return date.toLocaleDateString('en-IN', { month: 'short', year: 'numeric' });
}

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

function resetForm() {
    form.month = '';
    form.particulars = '';
    form.amount = '';
}

function openCreate() {
    editingId.value = null;
    resetForm();
    clearErrors();
    showModal.value = true;
}

function openEdit(expense) {
    editingId.value = expense.id;
    form.month = expense.month ?? '';
    form.particulars = expense.particulars ?? '';
    form.amount = expense.amount ?? '';
    clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingId.value = null;
    resetForm();
    clearErrors();
}

async function loadExpenses(page = pagination.currentPage) {
    if (!canView.value) {
        expenses.value = [];
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        const response = await listMonthlyExpenses({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
            month: monthFilter.value,
        });

        expenses.value = response.data;
        syncFromMeta(response.meta, expenses.value.length);
    } catch {
        toast.error('Could not load monthly expenses.');
        expenses.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadExpenses(page);
}

function applyValidationError(error, fallback = 'Unable to save monthly expense. Please try again.') {
    if (applyApiError(error, fallback)) {
        toast.error(formError.value || fallback);

        return;
    }

    toast.error(friendlyApiErrorMessage(error, fallback));
}

async function handleSubmit() {
    saving.value = true;
    clearErrors();

    const payload = {
        month: form.month,
        particulars: form.particulars.trim(),
        amount: form.amount,
    };

    try {
        if (isEdit.value) {
            await updateMonthlyExpense(editingId.value, payload);
            toast.success('Monthly expense updated successfully.');
        } else {
            await createMonthlyExpense(payload);
            toast.success('Monthly expense added successfully.');
        }

        closeModal();
        await loadExpenses(isEdit.value ? pagination.currentPage : 1);
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleDelete(expense) {
    if (!window.confirm(`Delete expense "${expense.particulars}" for ${formatMonth(expense.month)}?`)) {
        return;
    }

    try {
        await deleteMonthlyExpense(expense.id);
        toast.success('Monthly expense deleted successfully.');

        const page =
            expenses.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        await loadExpenses(page);
    } catch (error) {
        toast.error(friendlyApiErrorMessage(error, 'Unable to delete monthly expense. Please try again.'));
    }
}

onMounted(() => {
    if (canView.value) {
        loadExpenses();
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

    .filter-month-col {
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

    .filter-month-col {
        flex: 1 1 150px;
        min-width: 130px;
    }
}

.monthly-header-btn--create,
:deep(.monthly-header-btn--create),
.monthly-header-btn--create span,
:deep(.monthly-header-btn--create span),
.monthly-header-btn--create i,
:deep(.monthly-header-btn--create i) {
    color: var(--brand-primary, #2fb36f) !important;
    font-weight: 600 !important;
}

.monthly-header-btn--filter,
.monthly-header-btn--filter:hover,
.monthly-header-btn--filter:focus {
    color: var(--brand-primary, #2fb36f) !important;
}

@media (min-width: 768px) {
    .monthly-expenses-filters-content {
        display: block !important;
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
    .filter-month-col {
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

    :deep(.app-index-collapsible-filters .form-control) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder) {
        font-size: 0.75rem !important;
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
</style>
