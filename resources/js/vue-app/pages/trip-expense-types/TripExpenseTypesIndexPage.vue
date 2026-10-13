<template>
    <div class="container mt-4 app-index-page app-index-page--standard trip-expense-types-index-page">
        <AppIndexListCard title="Trip Expense Types" :loading="loading" loading-text="Loading trip expense types…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Trip Expense Type"
                    @click="openCreate"
                >
                    Add Expense Type
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="trip-expense-types-filters-panel"
                :loading="loading"
                @search="loadExpenseTypes(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="tripExpenseTypesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="tripExpenseTypesIndex_search"
                        placeholder="Search expense type"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadExpenseTypes(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px">S.No.</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th style="width: 120px">Status</th>
                        <th v-if="canEdit || canDelete" class="app-index-actions-cell text-end" style="width: 150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="expenseTypes.length === 0" class="app-index-empty-row">
                        <td :colspan="(canEdit || canDelete) ? 5 : 4" class="text-center text-muted py-4">No trip expense types found.</td>
                    </tr>
                    <tr v-for="(type, index) in expenseTypes" :key="type.id">
                        <td>{{ (pagination.currentPage - 1) * pagination.perPage + index + 1 }}</td>
                        <td>
                            <strong class="text-dark">{{ type.name }}</strong>
                        </td>
                        <td>
                            <code>{{ type.code }}</code>
                        </td>
                        <td>
                            <div class="form-check form-switch" v-if="canEdit">
                                <input
                                    class="form-check-input form-check-input-success"
                                    type="checkbox"
                                    role="switch"
                                    :checked="type.is_active"
                                    @change="toggleStatus(type)"
                                    style="cursor: pointer"
                                />
                            </div>
                            <template v-else>
                                <span v-if="type.is_active" class="badge bg-success-subtle text-success border border-success-subtle">
                                    Active
                                </span>
                                <span v-else class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    Inactive
                                </span>
                            </template>
                        </td>
                        <td v-if="canEdit || canDelete" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    action="edit"
                                    v-if="canEdit"
                                    @click="openEdit(type)"
                                />
                                <AppCrudActionButton
                                    action="delete"
                                    v-if="canDelete"
                                    @click="handleDelete(type)"
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
                    aria-label="Trip expense types pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <TripExpenseTypeFormModal
            :show="showModal"
            :saving="saving"
            :expense-type="editing"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeModal"
            @submit="handleSubmit"
        />


    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    createTripExpenseType,
    listTripExpenseTypes,
    updateTripExpenseType,
    deleteTripExpenseType,
} from '../../api/tripExpenseTypes';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import TripExpenseTypeFormModal from '../../components/trip-expense-types/TripExpenseTypeFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';

const toast = useAppToast();
const { can, user } = usePermissions();

const isSuperAdmin = computed(() => user.value?.role === 'super_admin');
const canCreate = computed(() => isSuperAdmin.value || can('trip_expense_types.create') || can('trips.expenses'));
const canEdit = computed(() => isSuperAdmin.value || can('trip_expense_types.edit') || can('trips.expenses'));
const canDelete = computed(() => isSuperAdmin.value || can('trip_expense_types.delete') || can('trips.expenses'));

const expenseTypes = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);
const showModal = ref(false);
const editing = ref(null);

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadExpenseTypes(1);
}

async function loadExpenseTypes(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listTripExpenseTypes({
            page,
            perPage: pagination.perPage,
            search: search.value.trim(),
        });

        expenseTypes.value = (response.data || []).filter(
            (item) => !['DIESEL', 'OTHER'].includes(item.code?.toUpperCase()),
        );
        syncFromMeta(response.meta, expenseTypes.value.length);
    } catch {
        toast.error('Could not load trip expense types.');
        expenseTypes.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadExpenseTypes(page);
}

function openCreate() {
    editing.value = null;
    clearErrors();
    showModal.value = true;
}

function openEdit(type) {
    editing.value = type;
    clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    clearErrors();
}

async function handleSubmit({ payload }) {
    clearErrors();
    saving.value = true;

    try {
        if (editing.value) {
            await updateTripExpenseType(editing.value.id, payload);
            toast.success('Trip expense type updated successfully.');
        } else {
            await createTripExpenseType(payload);
            toast.success('Trip expense type added successfully.');
        }

        closeModal();
        await loadExpenseTypes();
    } catch (error) {
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}


onMounted(() => {
    loadExpenseTypes();
});
async function toggleStatus(type) {
    try {
        await updateTripExpenseType(type.id, {
            name: type.name,
            code: type.code,
            is_active: !type.is_active,
        });
        type.is_active = !type.is_active;
        toast.success(`Expense type ${type.name} is now ${type.is_active ? 'active' : 'inactive'}`);
    } catch (error) {
        toast.error('Failed to update status');
    }
}

async function handleDelete(type) {
    if (!confirm(`Are you sure you want to delete the expense type '${type.name}'?`)) {
        return;
    }

    try {
        await deleteTripExpenseType(type.id);
        toast.success(`Expense type ${type.name} has been deleted.`);
        await loadExpenseTypes();
    } catch (error) {
        toast.error('Failed to delete expense type.');
    }
}
</script>
