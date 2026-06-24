<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Branch Management">
            <template v-if="canManage" #actions>
                <AppButton icon="bi bi-plus" label="Add Branch" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <AppIndexFiltersCard :active="filtersActive" @apply="applyFilters" @clear="clearFilters">
            <div>
                <label class="form-label">Search</label>
                <AppIndexSearchInput
                    v-model="searchQuery"
                    :input-id="`${pageId}_search`"
                    placeholder="Search branch, company, contact…"
                    max-width="100%"
                    :disabled="loading"
                />
            </div>
            <div>
                <label class="form-label">Company</label>
                <select v-model="companyFilter" class="form-select" :disabled="loading">
                    <option value="">All companies</option>
                    <option v-for="company in companies" :key="company.id" :value="company.id">
                        {{ company.name }}
                    </option>
                </select>
            </div>
        </AppIndexFiltersCard>

        <AppIndexListCard :loading="loading" loading-text="Loading branches…">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>Branch Name</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Head Office</th>
                        <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="branches.length === 0" class="app-index-empty-row">
                        <td :colspan="canManage ? 7 : 6" class="text-center text-muted py-4">
                            <span v-if="canManage">
                                No branches yet. Use Add Branch to register your first branch.
                            </span>
                            <span v-else>No branches found.</span>
                        </td>
                    </tr>
                    <tr v-for="(branch, index) in branches" :key="branch.id">
                        <td>{{ rowNumber(index) }}</td>
                        <td>
                            <strong>{{ branch.branch_name }}</strong>
                        </td>
                        <td>{{ branch.company?.name || branch.company_name || '—' }}</td>
                        <td>{{ branch.branch_contact_number || '—' }}</td>
                        <td>{{ branch.branch_address || '—' }}</td>
                        <td>
                            <span v-if="branch.head_office" class="badge bg-primary">Head Office</span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td v-if="canManage" class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton action="edit" @click="openEdit(branch)" />
                                <AppCrudActionButton action="delete" @click="handleDelete(branch)" />
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
                    item-label="branches"
                    @page-change="goToPage"
                    @update:per-page="onPerPageUpdate"
                />
            </template>
        </AppIndexListCard>

        <BranchFormModal
            :show="showForm"
            :mode="formMode"
            :branch="editingBranch"
            :companies="companies"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="branchFormError"
            @close="closeForm"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { listAllCompanies } from '../../api/companies';
import { createBranch, deleteBranch, listBranches, updateBranch } from '../../api/branches';
import BranchFormModal from '../../components/branches/BranchFormModal.vue';
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

const pageId = 'branchesIndex';
const { can } = usePermissions();
const canManage = computed(() => can('branches.manage'));

const companies = ref([]);
const branches = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingBranch = ref(null);
const searchQuery = ref('');
const companyFilter = ref('');
const flash = reactive({ success: '', error: '' });

const { fieldErrors, formError: branchFormError, clearErrors, applyApiError } = useApiFormErrors();
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);

const filtersActive = computed(
    () => searchQuery.value.trim() !== '' || companyFilter.value !== '',
);

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

async function loadCompanies() {
    try {
        companies.value = await listAllCompanies();
    } catch {
        companies.value = [];
    }
}

async function loadBranches(page = pagination.currentPage) {
    loading.value = true;
    flash.error = '';

    try {
        const response = await listBranches({
            page,
            per_page: pagination.perPage,
            search: searchQuery.value.trim() || undefined,
            company_id: companyFilter.value || undefined,
        });

        branches.value = response.data ?? [];
        syncFromMeta(response.meta, branches.value.length);
    } catch (error) {
        flash.error = error.response?.data?.message ?? 'Failed to load branches.';
        branches.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    pagination.currentPage = 1;
    loadBranches(1);
}

function clearFilters() {
    searchQuery.value = '';
    companyFilter.value = '';
    applyFilters();
}

function goToPage(page) {
    pagination.currentPage = page;
    loadBranches(page);
}

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    pagination.currentPage = 1;
    loadBranches(1);
}

function openCreate() {
    formMode.value = 'create';
    editingBranch.value = null;
    clearErrors();
    showForm.value = true;
}

function openEdit(branch) {
    formMode.value = 'edit';
    editingBranch.value = branch;
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    formMode.value = 'create';
    editingBranch.value = null;
    clearErrors();
}

async function handleSubmit(payload) {
    saving.value = true;
    clearErrors();

    try {
        if (formMode.value === 'edit' && editingBranch.value) {
            await updateBranch(editingBranch.value.id, payload);
            flash.success = 'Branch updated successfully.';
        } else {
            await createBranch(payload);
            flash.success = 'Branch created successfully.';
        }

        closeForm();
        await loadBranches();
    } catch (error) {
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(branch) {
    if (!window.confirm(`Delete branch "${branch.branch_name}"?`)) {
        return;
    }

    flash.error = '';

    try {
        await deleteBranch(branch.id);
        flash.success = 'Branch deleted successfully.';
        await loadBranches();
    } catch (error) {
        flash.error = error.response?.data?.message ?? 'Could not delete branch.';
    }
}

onMounted(async () => {
    await loadCompanies();
    await loadBranches();
});
</script>
