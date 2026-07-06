<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader :title="pageTitle">
            <template v-if="canCreateUsers" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="showCompanyAlertSettings" class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">
                            <i class="fas fa-bell me-2 text-brand-primary"></i>
                            Company Document Alerts
                        </h3>
                        <p class="text-muted small mb-0">
                            Turn channels on or off for the entered company. Enable alerts per user when creating or
                            editing them below.
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

        <div v-else-if="showCompanyAlertSettingsNotice" class="alert alert-info">
            Enter a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            to configure company-wide document alert channels.
        </div>

        <div class="card app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ listTitle }}</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading users…</div>
                <table v-else class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th v-if="showCompanyColumn">Company</th>
                            <th v-if="showBranchColumn">Branch</th>
                            <th v-if="showDocumentAlertsColumn">Document Alerts</th>
                            <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="users.length === 0">
                            <td
                                :colspan="tableColspan"
                                class="text-center text-muted"
                            >
                                No users found.
                            </td>
                        </tr>
                        <tr v-for="user in users" :key="user.id">
                            <td>
                                <strong>{{ user.name }}</strong>
                                <span v-if="user.is_protected" class="badge bg-secondary ms-1">Protected</span>
                            </td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.role_label }}</td>
                            <td v-if="showCompanyColumn">{{ user.tenant_name ?? '—' }}</td>
                            <td v-if="showBranchColumn">{{ user.branch_name ?? '—' }}</td>
                            <td v-if="showDocumentAlertsColumn">
                                <span v-if="user.document_alerts_live" class="badge bg-success-subtle text-success-emphasis me-1">
                                    Live
                                </span>
                                <span v-if="user.document_alerts_email" class="badge bg-info-subtle text-info-emphasis me-1">
                                    Email
                                </span>
                                <span
                                    v-if="!user.document_alerts_live && !user.document_alerts_email"
                                    class="text-muted small"
                                >
                                    Off
                                </span>
                            </td>
                            <td v-if="canManage" class="app-index-actions-cell">
                                <AppTableActions v-if="!user.is_protected">
                                    <AppCrudActionButton action="edit" @click="openEdit(user)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(user)" />
                                </AppTableActions>
                                <span v-else class="text-muted small">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AppIndexPaginationFooter
                page-id="usersIndex"
                :loading="loading"
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :per-page="pagination.perPage"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
                item-label="users"
                @page-change="goToPage"
                @update:per-page="onPerPageUpdate"
            />
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
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
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
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppIndexPaginationFooter from '../../components/ui/AppIndexPaginationFooter.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';

const { can, user } = usePermissions();
const toast = useAppToast();
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
const listTitle = computed(() =>
    managesSuperAdmins.value ? 'Super Admins List' : 'Company Users List',
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
    let cols = 3;

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
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);

async function loadUsers(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listManagedUsers({ page, per_page: pagination.perPage });
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

function onPerPageUpdate(perPage) {
    pagination.perPage = perPage;
    pagination.currentPage = 1;
    loadUsers(1);
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

        closeForm();
        await loadUsers();
    } catch (error) {
        if (!applyApiError(error)) {
            toast.error('Unable to save user.');
        }
    } finally {
        saving.value = false;
    }
}

async function handleDelete(user) {
    if (!window.confirm(`Delete user ${user.name}?`)) {
        return;
    }

    try {
        await deleteManagedUser(user.id);
        toast.success('User deleted.');
        await loadUsers();
    } catch {
        toast.error('Unable to delete user.');
    }
}

onMounted(async () => {
    await Promise.all([loadFormOptions(), loadUsers(), loadCompanyAlertSettings()]);
});
</script>
