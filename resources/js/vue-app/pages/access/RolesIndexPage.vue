<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader title="Role Management" />

        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card app-index-list-card">
            <div class="card-header">
                <h3 class="card-title mb-0">Roles List</h3>
            </div>
            <div class="card-body table-responsive">
                <div v-if="loading" class="text-center py-4 text-muted">Loading roles…</div>
                <table v-else class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th>System Name</th>
                            <th>Permissions</th>
                            <th class="app-index-actions-cell" style="width: 140px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.id">
                            <td>
                                <strong>{{ role.label }}</strong>
                                <span v-if="role.is_protected" class="badge bg-secondary ms-1">Protected</span>
                            </td>
                            <td><code>{{ role.name }}</code></td>
                            <td>{{ role.permissions_count ?? 0 }}</td>
                            <td class="app-index-actions-cell">
                                <AppTableActions>
                                    <AppCrudActionButton
                                        v-if="canManage"
                                        action="edit"
                                        title="Manage permissions"
                                        @click="openPermissions(role)"
                                    />
                                </AppTableActions>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <RolePermissionsModal
            modal-id="rolePermissionsModal"
            :show="showPermissions"
            :role="editingRole"
            :can-edit="canManage"
            :saving="saving"
            :form-error="permissionsFormError"
            @close="closePermissions"
            @submit="handleSyncPermissions"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { listUserRoles, syncRolePermissions } from '../../api/userRoles';
import RolePermissionsModal from '../../components/access/RolePermissionsModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';

const { can } = usePermissions();
const canManage = computed(() => can('roles.manage'));

const { formError: permissionsFormError, clearErrors, applyApiError } = useApiFormErrors();

const roles = ref([]);
const loading = ref(true);
const saving = ref(false);
const showPermissions = ref(false);
const editingRole = ref(null);
const flash = reactive({ success: '', error: '' });

function showToast(message, type = 'success') {
    flash.success = type === 'success' ? message : '';
    flash.error = type === 'error' ? message : '';
}

async function loadRoles() {
    loading.value = true;
    flash.error = '';

    try {
        roles.value = await listUserRoles();
    } catch {
        showToast('Could not load roles.', 'error');
        roles.value = [];
    } finally {
        loading.value = false;
    }
}

function openPermissions(role) {
    editingRole.value = { ...role };
    clearErrors();
    showPermissions.value = true;
}

function closePermissions() {
    showPermissions.value = false;
    editingRole.value = null;
    clearErrors();
}

async function handleSyncPermissions(permissionIds) {
    if (!editingRole.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        await syncRolePermissions(editingRole.value.id, permissionIds);
        showToast('Role permissions updated.');
        closePermissions();
        await loadRoles();
    } catch (error) {
        if (!applyApiError(error, 'Unable to update role permissions.')) {
            showToast('Unable to update role permissions.', 'error');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(loadRoles);
</script>
