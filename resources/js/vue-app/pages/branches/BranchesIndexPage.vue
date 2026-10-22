<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="routeNotice" class="alert alert-warning">{{ routeNotice }}</div>

        <AppIndexListCard title="Branch Management" :loading="loading" loading-text="Loading branches…">
            <template #toolbar>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Add Branch"
                    aria-label="Add Branch"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Add </span>Branch</span>
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

                <div v-show="filtersOpen" class="branches-filters-content">
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
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px" class="text-center text-nowrap">S.No.</th>
                        <th class="text-nowrap">Branch Name</th>
                        <th class="text-nowrap">Company</th>
                        <th class="text-nowrap">Contact</th>
                        <th class="text-nowrap">Address</th>
                        <th class="text-nowrap">Head Office</th>
                        <th class="app-index-actions-cell text-nowrap" style="width: 120px">Actions</th>
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
                        <td class="text-center text-nowrap">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">
                            <RouterLink
                                :to="{ name: 'branches.show', params: { id: branch.id } }"
                                class="branch-name-link"
                                title="View branch details"
                            >
                                {{ branch.branch_name }}
                            </RouterLink>
                            <span v-if="isActiveBranch(branch.id)" class="badge bg-success ms-1">Active</span>
                        </td>
                        <td class="text-nowrap">{{ branch.company?.name || branch.company_name || '—' }}</td>
                        <td class="text-nowrap">{{ branch.branch_contact_number || '—' }}</td>
                        <td class="text-nowrap">{{ branch.branch_address || '—' }}</td>
                        <td class="text-nowrap">
                            <span v-if="branch.head_office" class="badge bg-primary">Head Office</span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="app-index-actions-cell text-nowrap">
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

@media (max-width: 767.98px) {
    :deep(.card-header) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0.65rem 0.85rem !important;
    }

    :deep(.card-title) {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        margin-bottom: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    :deep(.app-index-card-toolbar) {
        width: auto !important;
        flex: 0 0 auto !important;
        margin-left: auto !important;
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 0.35rem !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: flex-end !important;
        gap: 6px !important;
        width: 100% !important;
        padding: 0.5rem 0.65rem !important;
    }

    :deep(.app-index-filters-fields) {
        display: contents !important;
    }

    /* Row 1: Search input box (left 50%) and Company dropdown (right 50%) in one line */
    .filter-search-col,
    :deep(.filter-search-col) {
        order: 1 !important;
        flex: 0 0 calc(50% - 3px) !important;
        width: calc(50% - 3px) !important;
        max-width: calc(50% - 3px) !important;
        min-width: 0 !important;
        margin: 0 !important;
    }

    .filter-select-col,
    :deep(.filter-select-col) {
        order: 2 !important;
        flex: 0 0 calc(50% - 3px) !important;
        width: calc(50% - 3px) !important;
        max-width: calc(50% - 3px) !important;
        min-width: 0 !important;
        margin: 0 !important;
    }

    /* Row 2: Search and Reset buttons in next line (full width, 50% each) */
    :deep(.app-index-collapsible-filters__actions) {
        order: 3 !important;
        width: 100% !important;
        flex: 0 0 100% !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 6px !important;
        margin-top: 0.25rem !important;
        margin-left: 0 !important;
        margin-bottom: 0 !important;
    }

    :deep(.app-index-search) {
        width: 100% !important;
        max-width: 100% !important;
    }

    :deep(.app-index-collapsible-filters .form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
        white-space: nowrap !important;
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
        width: 100% !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder),
    :deep(.app-index-collapsible-filters .select-trigger-label) {
        font-size: 0.75rem !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 3px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 3px) !important;
        height: 30px !important;
        min-height: 30px !important;
        padding: 0.2rem 0.5rem !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 4px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        border-radius: 6px !important;
        white-space: nowrap !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn .app-index-filter-btn-text),
    :deep(.app-index-collapsible-filters__btn .app-index-filter-btn-text) {
        display: inline !important;
        font-size: 0.75rem !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn i),
    :deep(.app-index-collapsible-filters__btn i) {
        font-size: 0.75rem !important;
        margin: 0 !important;
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
        white-space: nowrap !important;
    }

    .branch-name-link {
        font-size: 0.72rem !important;
    }

    .badge {
        font-size: 0.65rem !important;
        padding: 0.2rem 0.35rem !important;
    }

    :deep(.app-table-actions) {
        gap: 3px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-wrap: nowrap !important;
    }

    :deep(.app-table-actions .btn),
    :deep(.app-table-actions button) {
        height: 24px !important;
        min-height: 24px !important;
        font-size: 0.68rem !important;
        padding: 0.1rem 0.45rem !important;
        line-height: 1 !important;
        border-radius: 4px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    :deep(.app-crud-action-button),
    :deep(.app-index-action-btn) {
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
        min-height: 24px !important;
        font-size: 0.65rem !important;
        padding: 0 !important;
        border-radius: 4px !important;
    }

    :deep(.app-crud-action-button i),
    :deep(.app-index-action-btn i),
    :deep(.app-index-action-btn .fas) {
        font-size: 0.625rem !important;
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

@media (max-width: 380px) {
    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>
