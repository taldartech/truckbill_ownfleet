<template>
    <div class="container mt-4 user-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading user details…</div>

        <template v-else-if="userItem">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>User Information ({{ userItem.name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                 <RouterLink
                                    :to="{ name: 'access.users' }"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Users"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                                <AppCrudActionButton
                                    v-if="canManage && !userItem.is_protected"
                                    action="edit"
                                    @click="openEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canManage && !userItem.is_protected"
                                    action="delete"
                                    @click="handleDelete(userItem)"
                                />
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Name</span>
                                <div class="fw-semibold">{{ userItem.name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Email</span>
                                <div>{{ userItem.email }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Role</span>
                                <div>{{ userItem.role_label || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Company</span>
                                <div>{{ userItem.tenant_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Branch</span>
                                <div>{{ userItem.branch_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Status</span>
                                <div>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Record Info</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Created</span>
                                <div>{{ formatDateTime(userItem.created_at) }}</div>
                                <div v-if="userItem.created_by_name" class="text-muted">
                                    by {{ userItem.created_by_name }}
                                </div>
                                <div v-else class="text-muted">by —</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(userItem.updated_at) }}</div>
                                <div v-if="userItem.updated_by_name" class="text-muted">
                                    by {{ userItem.updated_by_name }}
                                </div>
                                <div v-else class="text-muted">by —</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            User not found or you do not have access.
        </div>

        <UserFormModal
            modal-id="userDetailFormModal"
            :show="showForm"
            mode="edit"
            :user="userItem"
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
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { listAllCompanies } from '../../api/companies';
import { fetchManagedUser, listAssignableRoles, updateManagedUser, deleteManagedUser } from '../../api/managedUsers';
import UserFormModal from '../../components/access/UserFormModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { promptDelete } = useDeleteConfirm();
const { can } = usePermissions();

const canManage = computed(() => can('super_admins.manage') || can('users.manage'));

const userItem = ref(null);
const assignableRoles = ref([]);
const companies = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError: userFormError, clearErrors } = useApiFormErrors();

async function loadUser() {
    const id = route.params.id;
    if (!id) {
        userItem.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        userItem.value = await fetchManagedUser(id);
    } catch {
        userItem.value = null;
        toast.error('Could not load user details.');
    } finally {
        loading.value = false;
    }
}

async function loadOptions() {
    try {
        assignableRoles.value = await listAssignableRoles();
        companies.value = await listAllCompanies();
    } catch {
        assignableRoles.value = [];
        companies.value = [];
    }
}

function openEdit() {
    clearErrors();
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    clearErrors();
}

async function handleSubmit(payload) {
    if (!userItem.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        userItem.value = await updateManagedUser(userItem.value.id, payload);
        toast.success('User updated.');
        closeForm();
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
            userFormError.value =
                error.response.data.message ?? 'Please check the highlighted fields.';
        } else {
            toast.error('Unable to save user.');
        }
    } finally {
        saving.value = false;
    }
}

function handleDelete(userVal) {
    const targetUser = userVal || userItem.value;
    if (!targetUser?.id) {
        return;
    }

    promptDelete({
        title: 'Delete User?',
        itemName: targetUser.name,
        details: [
            { label: 'Name', value: targetUser.name },
            { label: 'Email', value: targetUser.email },
            { label: 'Role', value: targetUser.role_label ?? targetUser.role },
            { label: 'Status', value: targetUser.is_active ? 'Active' : 'Inactive' },
        ],
        onConfirm: async (remark) => {
            await deleteManagedUser(targetUser.id, { deleted_reason: remark, remark });
            toast.success('User deleted.');
            router.push({ name: 'access.users' });
        },
    });
}

onMounted(async () => {
    await Promise.all([loadUser(), loadOptions()]);
});

watch(() => route.params.id, () => {
    loadUser();
});
</script>

<style scoped>
@media (max-width: 767.98px) {
    .user-detail-page {
        margin-top: 0.5rem !important;
    }

    .card-header {
        font-size: 0.82rem !important;
        padding: 0.5rem 0.75rem !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.35rem !important;
    }

    .card-header strong {
        font-size: 0.82rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .card-body {
        font-size: 0.75rem !important;
        padding: 0.65rem 0.75rem !important;
    }

    .card-body.row {
        --bs-gutter-x: 0.5rem !important;
        --bs-gutter-y: 0.5rem !important;
    }

    .card-body .col-md-4 {
        flex: 0 0 50% !important;
        max-width: 50% !important;
    }

    .card-body .text-muted {
        font-size: 0.68rem !important;
        display: block !important;
        margin-bottom: 0.1rem !important;
    }

    .card-body .fw-semibold,
    .card-body div {
        font-size: 0.74rem !important;
        word-break: break-word !important;
    }

    .app-index-action-btn,
    :deep(.app-crud-action-button) {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        min-height: 28px !important;
        font-size: 0.75rem !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
    }
}
</style>
