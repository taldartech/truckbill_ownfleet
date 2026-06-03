<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">{{ pageTitle }}</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" :label="addButtonLabel" @click="openCreate" />
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ listTitle }}</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading users…</div>
                <table v-else class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th v-if="showCompanyColumn">Company</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
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
                            <td v-if="canManage">
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
            <div v-if="pagination.lastPage > 1" class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }}
                </small>
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loading || pagination.currentPage <= 1"
                        @click="goToPage(pagination.currentPage - 1)"
                    >
                        Previous
                    </button>
                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Page {{ pagination.currentPage }} / {{ pagination.lastPage }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="loading || pagination.currentPage >= pagination.lastPage"
                        @click="goToPage(pagination.currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
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
import { listAllCompanies } from '../../api/companies';
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
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';

const { can, user } = usePermissions();
const managesSuperAdmins = computed(() => can('super_admins.view'));
const managesTenantUsers = computed(() => can('users.view'));
const canManage = computed(
    () => can('super_admins.manage') || can('users.manage'),
);
const pageTitle = computed(() =>
    managesSuperAdmins.value ? 'Super Admin Management' : 'User Management',
);
const listTitle = computed(() =>
    managesSuperAdmins.value ? 'Super Admins List' : 'Company Users List',
);
const addButtonLabel = computed(() =>
    managesSuperAdmins.value ? 'Add Super Admin' : 'Add User',
);
const showCompanyColumn = computed(() => managesTenantUsers.value && !managesSuperAdmins.value);
const tableColspan = computed(() => {
    let cols = 3;

    if (showCompanyColumn.value) {
        cols += 1;
    }

    if (canManage.value) {
        cols += 1;
    }

    return cols;
});

const { fieldErrors, formError: userFormError, clearErrors, applyApiError } = useApiFormErrors();

const users = ref([]);
const assignableRoles = ref([]);
const companies = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingUser = ref(null);
const flash = reactive({ success: '', error: '' });
const pagination = reactive({
    currentPage: 1,
    perPage: 15,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0,
});

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

async function loadUsers(page = pagination.currentPage) {
    loading.value = true;
    flash.error = '';

    try {
        const response = await listManagedUsers({ page, per_page: pagination.perPage });
        users.value = response.data ?? [];
        pagination.currentPage = response.meta?.current_page ?? page;
        pagination.lastPage = response.meta?.last_page ?? 1;
        pagination.total = response.meta?.total ?? users.value.length;
        pagination.from = response.meta?.from ?? (users.value.length > 0 ? 1 : 0);
        pagination.to = response.meta?.to ?? users.value.length;
    } catch {
        showToast('Could not load users.', 'error');
        users.value = [];
    } finally {
        loading.value = false;
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
    saving.value = true;
    clearErrors();

    try {
        if (formMode.value === 'edit' && editingUser.value?.id) {
            await updateManagedUser(editingUser.value.id, payload);
            showToast('User updated.');
        } else {
            await createManagedUser(payload);
            showToast('User created.');
        }

        closeForm();
        await loadUsers();
    } catch (error) {
        if (!applyApiError(error)) {
            showToast('Unable to save user.', 'error');
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
        showToast('User deleted.');
        await loadUsers();
    } catch {
        showToast('Unable to delete user.', 'error');
    }
}

onMounted(async () => {
    await Promise.all([loadFormOptions(), loadUsers()]);
});
</script>
