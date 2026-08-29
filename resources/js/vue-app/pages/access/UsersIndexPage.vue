<template>
    <div class="container mt-4 app-index-page app-index-page--standard users-index-page">
        <AppIndexListCard :title="pageTitle" :loading="loading" loading-text="Loading users…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreateUsers"
                    icon="plus"
                    aria-label="Create user"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

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

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px" class="text-center">S.No.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th v-if="showCompanyColumn">Company</th>
                        <th v-if="showBranchColumn">Branch</th>
                        <th>Status</th>
                        <th v-if="showDocumentAlertsColumn">Document Alerts</th>
                        <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.length === 0" class="app-index-empty-row">
                        <td :colspan="tableColspan" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                    <tr v-for="(userItem, index) in users" :key="userItem.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'users.show', params: { id: userItem.id } }"
                                class="user-name-link"
                                title="View user details"
                            >
                                {{ userItem.name }}
                            </RouterLink>
                            <span v-if="userItem.is_protected" class="badge bg-secondary ms-1">Protected</span>
                        </td>
                        <td>{{ userItem.email }}</td>
                        <td>{{ userItem.role_label }}</td>
                        <td v-if="showCompanyColumn">{{ userItem.tenant_name ?? '—' }}</td>
                        <td v-if="showBranchColumn">{{ userItem.branch_name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-success">Active</span>
                        </td>
                        <td v-if="showDocumentAlertsColumn">
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
                        <td v-if="canManage" class="app-index-actions-cell">
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

        <UserDeleteConfirmModal
            :show="showDeleteConfirm"
            :user="deletingUser"
            :deleting="deleting"
            :user-type-label="managesSuperAdmins ? 'Super Admin' : 'User'"
            @cancel="closeDeleteConfirm"
            @confirm="confirmDelete"
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
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
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
import UserDeleteConfirmModal from './UserDeleteCofirmModal.vue';

const router = useRouter();
const { can, user } = usePermissions();
const toast = useAppToast();
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
const showDeleteConfirm = ref(false);
const deletingUser = ref(null);
const deleting = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingUser = ref(null);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

const filters = reactive({
    search: '',
    user_role_id: '',
});

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

function handleDelete(user) {
    if (user.is_protected) {
        return;
    }

    deletingUser.value = user;
    showDeleteConfirm.value = true;
}

function closeDeleteConfirm() {
    if (deleting.value) {
        return;
    }

    showDeleteConfirm.value = false;
    deletingUser.value = null;
}

async function confirmDelete(payload) {
    if (!deletingUser.value) {
        return;
    }

    deleting.value = true;

    try {
        const userId = deletingUser.value.id;

        await deleteManagedUser(userId, payload);

        toast.success('User deleted.');

        const page =
            users.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        showDeleteConfirm.value = false;
        deletingUser.value = null;

        await loadUsers(page);
    } catch (error) {
        const message =
            error.response?.data?.errors?.deleted_reason?.[0] ??
            error.response?.data?.message ??
            'Unable to delete user.';

        toast.error(message);
    } finally {
        deleting.value = false;
    }
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
</style>
