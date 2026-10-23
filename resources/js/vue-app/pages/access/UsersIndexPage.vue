<template>
    <div class="container mt-4 app-index-page app-index-page--standard users-index-page">
        <AppIndexListCard :title="pageTitle" :loading="loading" loading-text="Loading users…">
            <template #toolbar>
                <button
                    v-if="canCreateUsers"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Create User"
                    aria-label="Create user"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Create </span>User</span>
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

                <div v-show="filtersOpen" class="users-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="users-filters-panel"
                        :loading="loading"
                        @search="loadUsers(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
                            <label class="form-label" for="usersIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="filters.search"
                                input-id="usersIndex_search"
                                placeholder="Search name, email…"
                                max-width="100%"
                                size="md"
                                :disabled="loading"
                                @keydown.enter="loadUsers(1)"
                            />
                        </div>
                        <div class="filter-select-col">
                            <label class="form-label">Role</label>
                            <AppSelect
                                v-model="filters.user_role_id"
                                :options="roleFilterOptions"
                                placeholder="All roles"
                                search-placeholder="Search role…"
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
                        <th class="text-nowrap">Name</th>
                        <th class="text-nowrap">Email</th>
                        <th class="text-nowrap">Role</th>
                        <th v-if="showCompanyColumn" class="text-nowrap">Company</th>
                        <th v-if="showBranchColumn" class="text-nowrap">Branch</th>
                        <th class="text-nowrap">Status</th>
                        <th v-if="showDocumentAlertsColumn" class="text-nowrap">Document Alerts</th>
                        <th v-if="canManage" class="app-index-actions-cell text-nowrap" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.length === 0" class="app-index-empty-row">
                        <td :colspan="tableColspan" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                    <tr v-for="(userItem, index) in users" :key="userItem.id">
                        <td class="text-center text-nowrap">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">
                            <RouterLink
                                :to="{ name: 'users.show', params: { id: userItem.id } }"
                                class="user-name-link"
                                title="View user details"
                            >
                                {{ userItem.name }}
                            </RouterLink>
                            <span v-if="userItem.is_protected" class="badge bg-secondary ms-1">Protected</span>
                        </td>
                        <td class="text-nowrap">{{ userItem.email }}</td>
                        <td class="text-nowrap">{{ userItem.role_label }}</td>
                        <td v-if="showCompanyColumn" class="text-nowrap">{{ userItem.tenant_name ?? '—' }}</td>
                        <td v-if="showBranchColumn" class="text-nowrap">{{ userItem.branch_name ?? '—' }}</td>
                        <td class="text-nowrap">
                            <span class="badge bg-success">Active</span>
                        </td>
                        <td v-if="showDocumentAlertsColumn" class="text-nowrap">
                            <span v-if="userItem.document_alerts_live" class="badge bg-success-subtle text-success-emphasis me-1">
                                Live
                            </span>
                            <span v-if="userItem.document_alerts_email" class="badge bg-info-subtle text-info-emphasis me-1">
                                Email
                            </span>
                            <span
                                v-if="!userItem.document_alerts_live && !userItem.document_alerts_email"
                                class="text-muted small"
                            >
                                Off
                            </span>
                        </td>
                        <td v-if="canManage" class="app-index-actions-cell text-nowrap">
                            <AppTableActions v-if="!userItem.is_protected">
                                <!-- <AppCrudActionButton
                                    action="view"
                                    @click="goToDetail(userItem)"
                                /> -->
                                <AppCrudActionButton action="edit" @click="openEdit(userItem)" />
                                <AppCrudActionButton action="delete" @click="handleDelete(userItem)" />
                            </AppTableActions>
                            <span v-else class="text-muted small">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="users"
                    aria-label="Users pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <div v-if="showCompanyAlertSettings" class="card mb-3 mt-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">
                            <i class="fas fa-bell me-2 text-brand-primary"></i>
                            Company Document Alerts
                        </h3>
                        <p class="text-muted small mb-0">
                            Turn channels on or off for the entered company. Enable alerts per user when creating or
                            editing them above.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn btn-brand-primary btn-sm"
                        :disabled="companySettingsSaving || companySettingsLoading"
                        @click="saveCompanyAlertSettings"
                    >
                        {{ companySettingsSaving ? 'Saving…' : 'Save channels' }}
                    </button>
                </div>

                <div v-if="companySettingsLoading" class="text-muted small">Loading company alert settings…</div>
                <div v-else-if="companySettingsError" class="alert alert-danger py-2 mb-0">
                    {{ companySettingsError }}
                </div>
                <div v-else class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input
                                id="company_notify_live"
                                v-model="companyAlertSettings.notify_live"
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                            />
                            <label class="form-check-label" for="company_notify_live">Live notifications</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input
                                id="company_notify_email"
                                v-model="companyAlertSettings.notify_email"
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                            />
                            <label class="form-check-label" for="company_notify_email">Email notifications</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="showCompanyAlertSettingsNotice" class="alert alert-info mt-3">
            Enter a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            to configure company-wide document alert channels.
        </div>

        <UserFormModal
            modal-id="userFormModal"
            :show="showForm"
            :mode="formMode"
            :user="editingUser"
            :assignable-roles="assignableRoles"
            :companies="companies"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="userFormError"
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
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { listAllBranches } from '../../api/branches';
import { listAllCompanies } from '../../api/companies';
import { fetchDocumentAlertSettings, updateDocumentAlertSettings } from '../../api/documentAlertSettings';
import {
    createManagedUser,
    deleteManagedUser,
    listAssignableRoles,
    listManagedUsers,
    updateManagedUser,
} from '../../api/managedUsers';
import UserFormModal from '../../components/access/UserFormModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildUserFields } from '../../utils/masterCrudSaveConfirm';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';

const router = useRouter();
const { can, user } = usePermissions();
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
const managesSuperAdmins = computed(() => can('super_admins.view'));
const managesTenantUsers = computed(() => can('users.view'));
const canManage = computed(
    () => can('super_admins.manage') || can('users.manage'),
);
const canCreateUsers = computed(
    () => canManage.value && assignableRoles.value.length > 0,
);
const pageTitle = computed(() =>
    managesSuperAdmins.value ? 'Super Admin Management' : 'User Management',
);
const showCompanyColumn = computed(() => managesTenantUsers.value && !managesSuperAdmins.value);
const showBranchColumn = computed(() => managesTenantUsers.value && !managesSuperAdmins.value);
const showDocumentAlertsColumn = computed(() => managesTenantUsers.value && !managesSuperAdmins.value);
const hasCompanyContext = computed(() => !user.value?.requires_company_selection);
const showCompanyAlertSettings = computed(
    () => can('users.manage') && managesTenantUsers.value && hasCompanyContext.value,
);
const showCompanyAlertSettingsNotice = computed(
    () => can('users.manage') && managesTenantUsers.value && !hasCompanyContext.value,
);
const tableColspan = computed(() => {
    let cols = 4; // Name, Email, Role, Status

    if (showCompanyColumn.value) {
        cols += 1;
    }

    if (showBranchColumn.value) {
        cols += 1;
    }

    if (showDocumentAlertsColumn.value) {
        cols += 1;
    }

    if (canManage.value) {
        cols += 1;
    }

    return cols;
});

const companyAlertSettings = reactive({
    notify_live: true,
    notify_email: true,
});
const companySettingsLoading = ref(false);
const companySettingsSaving = ref(false);
const companySettingsError = ref('');

const { fieldErrors, formError: userFormError, clearErrors, applyApiError } = useApiFormErrors();

const users = ref([]);
const assignableRoles = ref([]);
const companies = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingUser = ref(null);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filters = reactive({
    search: '',
    user_role_id: '',
});
const filtersOpen = ref(false);

const roleFilterOptions = computed(() =>
    assignableRoles.value.map((role) => ({
        value: role.id,
        label: role.label,
    })),
);

function clearFilters() {
    filters.search = '';
    filters.user_role_id = '';
    loadUsers(1);
}

function rowNumber(index) {
    return (pagination.currentPage - 1) * pagination.perPage + index + 1;
}

function goToDetail(userItem) {
    router.push({ name: 'users.show', params: { id: userItem.id } });
}

async function loadUsers(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listManagedUsers({
            page,
            per_page: pagination.perPage,
            search: filters.search.trim() || undefined,
            user_role_id: filters.user_role_id || undefined,
        });
        users.value = response.data ?? [];
        syncFromMeta(response.meta, users.value.length);
    } catch {
        toast.error('Could not load users.');
        users.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

async function loadCompanyAlertSettings() {
    if (!showCompanyAlertSettings.value) {
        return;
    }

    companySettingsLoading.value = true;
    companySettingsError.value = '';

    try {
        const data = await fetchDocumentAlertSettings();
        companyAlertSettings.notify_live = data.settings?.notify_live ?? true;
        companyAlertSettings.notify_email = data.settings?.notify_email ?? true;
    } catch {
        companySettingsError.value = 'Could not load company alert settings.';
    } finally {
        companySettingsLoading.value = false;
    }
}

async function saveCompanyAlertSettings() {
    companySettingsSaving.value = true;
    companySettingsError.value = '';

    try {
        await updateDocumentAlertSettings({
            notify_live: companyAlertSettings.notify_live,
            notify_email: companyAlertSettings.notify_email,
        });
        toast.success('Company alert channels saved.');
    } catch {
        companySettingsError.value = 'Could not save company alert settings.';
        toast.error('Could not save company alert settings.');
    } finally {
        companySettingsSaving.value = false;
    }
}

async function loadFormOptions() {
    try {
        assignableRoles.value = await listAssignableRoles();

        if (managesTenantUsers.value) {
            companies.value = await listAllCompanies();
        } else {
            companies.value = [];
        }
    } catch {
        assignableRoles.value = [];
        companies.value = [];
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadUsers(page);
}

function openCreate() {
    formMode.value = 'create';
    editingUser.value = null;
    clearErrors();
    showForm.value = true;
}

function openEdit(user) {
    formMode.value = 'edit';
    editingUser.value = { ...user };
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingUser.value = null;
    clearErrors();
}

async function handleSubmit(payload) {
    clearErrors();

    let branches = [];

    try {
        branches = await listAllBranches();
    } catch {
        branches = [];
    }

    promptSaveConfirm({
        payload,
        entityLabel: managesSuperAdmins.value ? 'Super Admin' : 'User',
        mode: formMode.value === 'edit' ? 'edit' : 'create',
        fields: buildUserFields(payload, editingUser.value, {
            roles: assignableRoles.value,
            companies: companies.value,
            branches,
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
        if (formMode.value === 'edit' && editingUser.value?.id) {
            await updateManagedUser(editingUser.value.id, payload);
            toast.success('User updated.');
        } else {
            await createManagedUser(payload);
            toast.success('User created.');
        }

        finishSaveConfirm();
        closeForm();
        await loadUsers();
    } catch (error) {
        finishSaveConfirm();

        if (!applyApiError(error)) {
            toast.error('Unable to save user.');
        }
    } finally {
        saving.value = false;
    }
}

function handleDelete(userItem) {
    if (userItem.is_protected) {
        return;
    }

    promptDelete({
        title: `Delete ${managesSuperAdmins.value ? 'Super Admin' : 'User'}?`,
        itemName: userItem.name,
        details: [
            { label: 'Name', value: userItem.name },
            { label: 'Email', value: userItem.email },
            { label: 'Role', value: userItem.role_label ?? userItem.role },
            { label: 'Status', value: userItem.is_active ? 'Active' : 'Inactive' },
        ],
        onConfirm: async (remark) => {
            await deleteManagedUser(userItem.id, { deleted_reason: remark, remark });
            toast.success('User deleted.');

            const page =
                users.value.length === 1 && pagination.currentPage > 1
                    ? pagination.currentPage - 1
                    : pagination.currentPage;

            await loadUsers(page);
        },
    });
}

onMounted(async () => {
    await Promise.all([loadFormOptions(), loadUsers(), loadCompanyAlertSettings()]);
});
</script>

<style scoped>
.user-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.user-name-link:hover {
    text-decoration: underline;
}

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

    /* Row 1: Search input box (left 50%) and Role dropdown (right 50%) in one line */
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

    .user-name-link {
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

    .alert,
    :deep(.alert) {
        font-size: 0.72rem !important;
        line-height: 1.35 !important;
        padding: 0.45rem 0.65rem !important;
        margin-top: 0.45rem !important;
        margin-bottom: 0.45rem !important;
        border-radius: 6px !important;
    }

    .alert .alert-link,
    :deep(.alert .alert-link) {
        font-size: 0.72rem !important;
        font-weight: 600 !important;
    }

    .card.app-index-list-card .card-body {
        padding: 0.5rem 0.65rem !important;
    }

    .card.app-index-list-card h6 {
        font-size: 0.78rem !important;
        font-weight: 700 !important;
        margin-bottom: 0.2rem !important;
    }

    .card.app-index-list-card p {
        font-size: 0.68rem !important;
        line-height: 1.3 !important;
        margin-bottom: 0.35rem !important;
    }

    .card.app-index-list-card .btn {
        height: 26px !important;
        font-size: 0.72rem !important;
        padding: 0.15rem 0.5rem !important;
        border-radius: 4px !important;
    }

    .card.app-index-list-card .form-check-label {
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
