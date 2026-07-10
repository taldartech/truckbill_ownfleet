<template>
    <div class="container mt-4 app-index-page app-index-page--standard permissions-index-page">
        <AppIndexListCard title="Permission Management" :loading="loading" loading-text="Loading permissions…">
            <template #toolbar>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="permissions-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canManage"
                    icon="plus"
                    aria-label="Create permission"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="permissions-filters-panel"
                :loading="loading"
                @search="loadPermissions(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="permissionsIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="permissionsIndex_search"
                        placeholder="Search slug or name"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadPermissions(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th>Slug</th>
                        <th>Name</th>
                        <th>Group</th>
                        <th>Roles</th>
                        <th v-if="canManage" class="app-index-actions-cell" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="permissions.length === 0" class="app-index-empty-row">
                        <td :colspan="tableColspan" class="text-center text-muted py-4">No permissions found.</td>
                    </tr>
                    <tr v-for="permission in permissions" :key="permission.id">
                        <td><code>{{ permission.slug }}</code></td>
                        <td>{{ permission.name }}</td>
                        <td>{{ permission.group }}</td>
                        <td>{{ permission.roles_count ?? 0 }}</td>
                        <td v-if="canManage" class="app-index-actions-cell">
                            <AppTableActions>
                                <AppCrudActionButton action="edit" @click="openEdit(permission)" />
                                <AppCrudActionButton action="delete" @click="handleDelete(permission)" />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="permissions"
                    aria-label="Permissions pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

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
import { computed, onMounted, ref } from 'vue';
import {
    createPermission,
    deletePermission,
    listPermissions,
    updatePermission,
} from '../../api/permissions';
import PermissionFormModal from '../../components/access/PermissionFormModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { buildPermissionFields } from '../../utils/masterCrudSaveConfirm';
import { useApiFormErrors } from '../../composables/useApiFormErrors';

const { can } = usePermissions();
const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const canManage = computed(() => can('permissions.manage'));
const tableColspan = computed(() => (canManage.value ? 5 : 4));

const { fieldErrors, formError: permissionFormError, clearErrors, applyApiError } = useApiFormErrors();

const permissions = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingPermission = ref(null);
const search = ref('');
const filtersOpen = ref(false);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadPermissions(1);
}

async function loadPermissions(page = pagination.currentPage) {
    loading.value = true;

    try {
        const params = { page, per_page: pagination.perPage };

        if (search.value.trim()) {
            params.search = search.value.trim();
        }

        const response = await listPermissions(params);
        permissions.value = response.data ?? [];
        syncFromMeta(response.meta, permissions.value.length);
    } catch {
        toast.error('Could not load permissions.');
        permissions.value = [];
        resetPagination();
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

function handleSubmit(payload) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Permission',
        mode: formMode.value === 'edit' ? 'edit' : 'create',
        fields: buildPermissionFields(payload, editingPermission.value),
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
        if (formMode.value === 'edit' && editingPermission.value?.id) {
            await updatePermission(editingPermission.value.id, payload);
            toast.success('Permission updated.');
        } else {
            await createPermission(payload);
            toast.success('Permission created.');
        }

        finishSaveConfirm();
        closeForm();
        await loadPermissions();
    } catch (error) {
        finishSaveConfirm();

        if (!applyApiError(error)) {
            toast.error('Unable to save permission.');
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
        toast.success('Permission deleted.');
        await loadPermissions();
    } catch {
        toast.error('Unable to delete permission.');
    }
}

onMounted(() => loadPermissions());
</script>
