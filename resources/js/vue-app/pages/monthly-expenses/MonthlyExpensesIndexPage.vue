<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Monthly Expenses" :loading="loading" loading-text="Loading monthly expenses…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="monthly-expenses-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    aria-label="Add monthly expense"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="monthly-expenses-filters-panel"
                :loading="loading"
                @search="loadExpenses(1)"
                @reset="clearFilters"
            >
                <div>
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
                <div>
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

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Month</th>
                        <th>Particulars</th>
                        <th class="text-end">Amount</th>
                        <th v-if="canManage" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="expenses.length === 0" class="app-index-empty-row">
                        <td :colspan="canManage ? 5 : 4" class="text-center text-muted py-4">
                            No monthly expenses found.
                        </td>
                    </tr>
                    <tr v-for="(expense, index) in expenses" :key="expense.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>{{ formatMonth(expense.month) }}</td>
                        <td>{{ expense.particulars }}</td>
                        <td class="text-end">{{ formatAmount(expense.amount) }}</td>
                        <td v-if="canManage" class="app-index-actions-cell text-end">
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

        <MonthlyExpenseDeleteConfirmModal
            :show="showDeleteConfirm"
            :expense="deletingExpense"
            :deleting="deleting"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
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
import MonthlyExpenseDeleteConfirmModal from './MonthlyExpenseDeleteConfirmmodal.vue';

const toast = useAppToast();
const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();
const { canInCompany } = usePermissions();
const canView = computed(() => canInCompany('monthly_expenses.view'));
const canManage = computed(() => canInCompany('monthly_expenses.manage'));

const expenses = ref([]);
const loading = ref(true);
const saving = ref(false);
const showDeleteConfirm = ref(false);
const deleting = ref(false);
const deletingExpense = ref(null);
const showModal = ref(false);
const editingId = ref(null);
const searchQuery = ref('');
const monthFilter = ref('');
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

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

function handleDelete(expense) {
    deletingExpense.value = expense;
    showDeleteConfirm.value = true;
}

function cancelDelete() {
    if (deleting.value) {
        return;
    }

    showDeleteConfirm.value = false;
    deletingExpense.value = null;
}

async function confirmDelete(payload) {
    if (!deletingExpense.value?.id) {
        return;
    }

    deleting.value = true;

    try {
        await deleteMonthlyExpense(
            deletingExpense.value.id,
            {
                deleted_reason: payload.deleted_reason,
            },
        );

        toast.success('Monthly expense deleted successfully.');

        showDeleteConfirm.value = false;
        deletingExpense.value = null;

        const page =
            expenses.value.length === 1 &&
            pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        await loadExpenses(page);
    } catch (error) {
        toast.error(
            friendlyApiErrorMessage(
                error,
                'Unable to delete monthly expense. Please try again.',
            ),
        );
    } finally {
        deleting.value = false;
    }
}

onMounted(() => {
    if (canView.value) {
        loadExpenses();
    } else {
        loading.value = false;
    }
});
</script>
