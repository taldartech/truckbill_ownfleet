<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="routeNotice" class="alert alert-warning">{{ routeNotice }}</div>

        <AppIndexListCard title="Branch Management" :loading="loading" loading-text="Loading branches…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="branches-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    aria-label="Add Branch"
                    @click="openCreate"
                >
                    Add Branch
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="branches-filters-panel"
                :loading="loading"
                @search="loadBranches(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="branchesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="searchQuery"
                        input-id="branchesIndex_search"
                        placeholder="Search branch, company, contact…"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadBranches(1)"
                    />
                </div>
                <div>
                    <label class="form-label">Company</label>
                    <AppSelect
                        v-model="companyFilter"
                        :options="companySelectOptions"
                        placeholder="All companies"
                        search-placeholder="Search company…"
                        :disabled="loading"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>Branch Name</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Head Office</th>
                        <th class="app-index-actions-cell text-end" style="width: 220px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="branches.length === 0" class="app-index-empty-row">
                        <td :colspan="7" class="text-center text-muted py-4">
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
                            <span v-if="isActiveBranch(branch.id)" class="badge bg-success ms-1">Active</span>
                        </td>
                        <td>{{ branch.company?.name || branch.company_name || '—' }}</td>
                        <td>{{ branch.branch_contact_number || '—' }}</td>
                        <td>{{ branch.branch_address || '—' }}</td>
                        <td>
                            <span v-if="branch.head_office" class="badge bg-primary">Head Office</span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="app-index-actions-cell text-end">
                            <div class="app-index-table-actions">
                                <AppButton
                                    v-if="canEnter"
                                    size="sm"
                                    variant="success"
                                    label="Enter"
                                    :disabled="isActiveBranch(branch.id)"
                                    @click="handleEnter(branch)"
                                />
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="btn btn-sm btn-info text-white app-index-action-btn"
                                    title="Edit"
                                    aria-label="Edit"
                                    @click="openEdit(branch)"
                                >
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="btn btn-sm btn-danger app-index-action-btn"
                                    title="Delete"
                                    aria-label="Delete"
                                    @click="handleDelete(branch)"
                                >
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="branches"
                    aria-label="Branches pagination"
                    @page-change="goToPage"
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

        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import { listAllCompanies } from '../../api/companies';
import { createBranch, deleteBranch, enterBranch, fetchBranchDeletionImpact, listBranches, updateBranch } from '../../api/branches';
import BranchFormModal from '../../components/branches/BranchFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { usePermissions } from '../../composables/usePermissions';
import { buildBranchFields } from '../../utils/masterCrudSaveConfirm';

const router = useRouter();
const route = useRoute();
const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const currentUser = inject('currentUser', ref(null));
const { can } = usePermissions();
const canManage = computed(() => can('branches.manage'));
const canEnter = computed(() => can('branches.enter'));

const activeBranchId = computed(
    () => currentUser.value?.effective_branch_id ?? currentUser.value?.branch_id ?? null,
);

const routeNotice = computed(() => route.query.notice ?? '');

function isActiveBranch(id) {
    return Number(activeBranchId.value) === Number(id);
}

const companies = ref([]);
const companySelectOptions = computed(() =>
    companies.value.map((company) => ({
        id: company.id,
        label: company.name,
    })),
);
const branches = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingBranch = ref(null);
const searchQuery = ref('');
const companyFilter = ref('');
const filtersOpen = ref(false);

const { fieldErrors, formError: branchFormError, clearErrors, applyApiError } = useApiFormErrors();
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const { states, initialize: initializeLocations } = useLocationLookups();

const filtersActive = computed(
    () => searchQuery.value.trim() !== '' || companyFilter.value !== '',
);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

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
        toast.error(error.response?.data?.message ?? 'Failed to load branches.');
        branches.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    searchQuery.value = '';
    companyFilter.value = '';
    pagination.currentPage = 1;
    loadBranches(1);
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadBranches(page);
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

function handleSubmit(payload) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Branch',
        mode: formMode.value === 'edit' ? 'edit' : 'create',
        fields: buildBranchFields(payload, editingBranch.value, {
            companies: companies.value,
            states: states.value,
        }),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        if (formMode.value === 'edit' && editingBranch.value) {
            await updateBranch(editingBranch.value.id, payload);
            toast.success('Branch updated successfully.');
        } else {
            await createBranch(payload);
            toast.success('Branch created successfully.');
        }

        finishSaveConfirm();
        closeForm();
        await loadBranches();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleEnter(branch) {
    try {
        const data = await enterBranch(branch.id);

        if (data?.user) {
            currentUser.value = data.user;
            window.__AUTH_USER__ = data.user;
        } else {
            const user = await fetchCurrentUser();
            currentUser.value = user;
            window.__AUTH_USER__ = user;
        }

        toast.success(`Entered ${branch.branch_name}.`);
        await router.push({ name: 'dashboard' });
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not enter branch.');
    }
}

async function handleDelete(branch) {
    let impact;

    try {
        impact = await fetchBranchDeletionImpact(branch.id);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not load branch delete details.');

        return;
    }

    if (!window.confirm(buildBranchDeleteMessage(branch.branch_name, impact))) {
        return;
    }

    try {
        const data = await deleteBranch(branch.id);

        if (data?.user) {
            currentUser.value = data.user;
            window.__AUTH_USER__ = data.user;
        } else if (isActiveBranch(branch.id)) {
            const user = await fetchCurrentUser();
            currentUser.value = user;
            window.__AUTH_USER__ = user;
            await router.push({ name: 'branches.index' });
        }

        toast.success('Branch deleted successfully.');
        await loadBranches();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete branch.');
    }
}

function buildBranchDeleteMessage(branchName, impact) {
    const lines = [`Delete branch "${branchName}"?`, ''];
    const resourceParts = [];

    if ((impact?.trucks_count ?? 0) > 0) {
        resourceParts.push(`${impact.trucks_count} truck(s)`);
    }

    if ((impact?.drivers_count ?? 0) > 0) {
        resourceParts.push(`${impact.drivers_count} driver(s)`);
    }

    if (resourceParts.length > 0) {
        lines.push(`This branch has ${resourceParts.join(' and ')}.`);
    } else {
        lines.push('This branch has no trucks or drivers.');
    }

    if ((impact?.users_count ?? 0) > 0) {
        lines.push(
            `${impact.users_count} assigned user(s) will be unassigned and cannot log in until reassigned.`,
        );
    }

    lines.push('Operational data stays in the system but will not show under any active branch.');

    return lines.join('\n');
}

onMounted(async () => {
    await initializeLocations();
    await loadCompanies();
    await loadBranches();

    if (filtersActive.value) {
        filtersOpen.value = true;
    }
});
</script>
