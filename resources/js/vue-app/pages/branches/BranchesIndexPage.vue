<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="routeNotice" class="alert alert-warning">{{ routeNotice }}</div>

        <AppIndexListCard title="Branch Management" :loading="loading" loading-text="Loading branches…">
            <template #toolbar>
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
                panel-id="branches-filters-panel"
                :loading="loading"
                @search="loadBranches(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="branchesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="searchQuery"
                        input-id="branchesIndex_search"
                        placeholder="Search branch, company"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadBranches(1)"
                    />
                </div>
                <div class="filter-select-col">
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
                        <th style="width: 70px" class="text-center">S.No.</th>
                        <th>Branch Name</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Head Office</th>
                        <th class="app-index-actions-cell" style="width: 120px">Actions</th>
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
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'branches.show', params: { id: branch.id } }"
                                class="branch-name-link"
                                title="View branch details"
                            >
                                {{ branch.branch_name }}
                            </RouterLink>
                            <span v-if="isActiveBranch(branch.id)" class="badge bg-success ms-1">Active</span>
                        </td>
                        <td>{{ branch.company?.name || branch.company_name || '—' }}</td>
                        <td>{{ branch.branch_contact_number || '—' }}</td>
                        <td>{{ branch.branch_address || '—' }}</td>
                        <td>
                            <span v-if="branch.head_office" class="badge bg-primary">Head Office</span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="app-index-actions-cell">
                            <AppTableActions>
                                <!--<AppCrudActionButton
                                    action="view"
                                    @click="goToDetail(branch)"
                                />-->
                                <AppButton
                                    v-if="canEnter"
                                    size="sm"
                                    variant="success"
                                    label="Enter"
                                    :disabled="isActiveBranch(branch.id)"
                                    @click="handleEnter(branch)"
                                />
                                <AppCrudActionButton
                                    v-if="canManage"
                                    action="edit"
                                    @click="openEdit(branch)"
                                />
                                <AppCrudActionButton
                                    v-if="canManage"
                                    action="delete"
                                    @click="handleDelete(branch)"
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
import AppTableActions from '../../components/ui/AppTableActions.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { usePermissions } from '../../composables/usePermissions';
import { buildBranchFields } from '../../utils/masterCrudSaveConfirm';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';

const router = useRouter();
const route = useRoute();
const toast = useAppToast();
const { promptDelete } = useDeleteConfirm();
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

const { fieldErrors, formError: branchFormError, clearErrors, applyApiError } = useApiFormErrors();
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const { states, initialize: initializeLocations } = useLocationLookups();

const filtersActive = computed(
    () => searchQuery.value.trim() !== '' || companyFilter.value !== '',
);

function rowNumber(index) {
    return (pagination.currentPage - 1) * pagination.perPage + index + 1;
}

function goToDetail(branch) {
    router.push({ name: 'branches.show', params: { id: branch.id } });
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
    let impact = null;
    try {
        impact = await fetchBranchDeletionImpact(branch.id);
    } catch {
        // proceed even if impact summary fails to load
    }

    const details = [
        { label: 'Branch Name', value: branch.branch_name },
        { label: 'Company', value: branch.company_name },
        { label: 'Trucks', value: impact ? impact.trucks_count : null },
        { label: 'Drivers', value: impact ? impact.drivers_count : null },
        { label: 'Assigned Users', value: impact ? impact.users_count : null },
    ];

    const warningNotice = impact?.users_count > 0
        ? `${impact.users_count} assigned user(s) will be unassigned and cannot log in until reassigned.`
        : null;

    promptDelete({
        title: 'Delete Branch?',
        itemName: `${branch.branch_name} (${branch.company_name})`,
        details,
        warningNotice,
        onConfirm: async (remark) => {
            const data = await deleteBranch(branch.id, {
                deleted_reason: remark,
                remark,
            });

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

            const page =
                branches.value.length === 1 && pagination.currentPage > 1
                    ? pagination.currentPage - 1
                    : pagination.currentPage;

            await loadBranches(page);
        },
    });
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
});
</script>

<style scoped>
/* Custom filters single row layout */
:deep(.app-index-collapsible-filters__inner) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

:deep(.app-index-filters-fields) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    flex: 1 1 auto;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

.filter-search-col {
    flex: 0 0 280px;
    width: 280px;
}

.filter-select-col {
    flex: 0 0 180px;
    width: 180px;
}

.branch-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.branch-name-link:hover {
    text-decoration: underline;
}
</style>
