<template>
    <div class="container mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Permission Management</h3>
            <AppButton v-if="canManage" icon="bi bi-plus" label="Add Permission" @click="openCreate" />
        </div>

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Permissions List</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading permissions…</div>
                <table v-else class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Slug</th>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Roles</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="permissions.length === 0">
                            <td :colspan="canManage ? 5 : 4" class="text-center text-muted">No permissions found.</td>
                        </tr>
                        <tr v-for="permission in permissions" :key="permission.id">
                            <td><code>{{ permission.slug }}</code></td>
                            <td>{{ permission.name }}</td>
                            <td>{{ permission.group }}</td>
                            <td>{{ permission.roles_count ?? 0 }}</td>
                            <td v-if="canManage">
                                <AppTableActions>
                                    <AppCrudActionButton action="edit" @click="openEdit(permission)" />
                                    <AppCrudActionButton action="delete" @click="handleDelete(permission)" />
                                </AppTableActions>
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

        <PermissionFormModal
            modal-id="permissionFormModal"
            :show="showForm"
            :mode="formMode"
            :permission="editingPermission"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="permissionFormError"
            @close="closeForm"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    createPermission,
    deletePermission,
    listPermissions,
    updatePermission,
} from '../../api/permissions';
import PermissionFormModal from '../../components/access/PermissionFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';

const { can } = usePermissions();
const canManage = computed(() => can('permissions.manage'));

const { fieldErrors, formError: permissionFormError, clearErrors, applyApiError } = useApiFormErrors();

const permissions = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingPermission = ref(null);
const flash = reactive({ success: '', error: '' });
const pagination = reactive({
    currentPage: 1,
    perPage: 50,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0,
});

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

async function loadPermissions(page = pagination.currentPage) {
    loading.value = true;
    flash.error = '';

    try {
        const response = await listPermissions({ page, per_page: pagination.perPage });
        permissions.value = response.data ?? [];
        pagination.currentPage = response.meta?.current_page ?? page;
        pagination.lastPage = response.meta?.last_page ?? 1;
        pagination.total = response.meta?.total ?? permissions.value.length;
        pagination.from = response.meta?.from ?? (permissions.value.length > 0 ? 1 : 0);
        pagination.to = response.meta?.to ?? permissions.value.length;
    } catch {
        showToast('Could not load permissions.', 'error');
        permissions.value = [];
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadPermissions(page);
}

function openCreate() {
    formMode.value = 'create';
    editingPermission.value = null;
    clearErrors();
    showForm.value = true;
}

function openEdit(permission) {
    formMode.value = 'edit';
    editingPermission.value = { ...permission };
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingPermission.value = null;
    clearErrors();
}

async function handleSubmit(payload) {
    saving.value = true;
    clearErrors();

    try {
        if (formMode.value === 'edit' && editingPermission.value?.id) {
            await updatePermission(editingPermission.value.id, payload);
            showToast('Permission updated.');
        } else {
            await createPermission(payload);
            showToast('Permission created.');
        }

        closeForm();
        await loadPermissions();
    } catch (error) {
        if (!applyApiError(error)) {
            showToast('Unable to save permission.', 'error');
        }
    } finally {
        saving.value = false;
    }
}

async function handleDelete(permission) {
    if (!window.confirm(`Delete permission "${permission.slug}"?`)) {
        return;
    }

    try {
        await deletePermission(permission.id);
        showToast('Permission deleted.');
        await loadPermissions();
    } catch {
        showToast('Unable to delete permission.', 'error');
    }
}

onMounted(() => loadPermissions());
</script>
