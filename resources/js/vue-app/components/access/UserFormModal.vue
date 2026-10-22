<template>
    <AppModal
        :show="show"
        :loading="saving"
        :form-error="formError"
        as-form
        @close="close"
        @submit="submit"
    >
        <template #title>
            <i :class="isEdit ? 'bi bi-pencil-square' : 'bi bi-person-plus'"></i>
            {{ isEdit ? 'Edit User' : 'Add User' }}
        </template>

        <div class="row mb-3">
            <div class="col-md-6">
                <label :for="`${modalId}_name`" class="form-label">Name *</label>
                <input
                    :id="`${modalId}_name`"
                    v-model="form.name"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.name }"
                    required
                />
                <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                    {{ fieldErrors.name[0] }}
                </div>
            </div>
            <div class="col-md-6">
                <label :for="`${modalId}_email`" class="form-label">Email *</label>
                <input
                    :id="`${modalId}_email`"
                    v-model="form.email"
                    type="email"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.email }"
                    required
                />
                <div v-if="fieldErrors.email" class="invalid-feedback d-block">
                    {{ fieldErrors.email[0] }}
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label :for="`${modalId}_role`" class="form-label">Role *</label>
                <AppSelect
                    :input-id="`${modalId}_role`"
                    v-model="form.user_role_id"
                    :options="roleSelectOptions"
                    placeholder="Select role"
                    search-placeholder="Search role…"
                    :disabled="isProtectedUser"
                    :invalid="Boolean(fieldErrors.user_role_id)"
                    :error-message="fieldErrors.user_role_id?.[0] ?? ''"
                />
            </div>
            <div v-if="requiresCompany" class="col-md-6">
                <label :for="`${modalId}_tenant`" class="form-label">Company *</label>
                <AppSelect
                    :input-id="`${modalId}_tenant`"
                    v-model="form.tenant_id"
                    :options="companySelectOptions"
                    placeholder="Select company"
                    search-placeholder="Search company…"
                    :invalid="Boolean(fieldErrors.tenant_id)"
                    :error-message="fieldErrors.tenant_id?.[0] ?? ''"
                />
            </div>
            <div v-if="requiresBranch" class="col-md-6">
                <label :for="`${modalId}_branch`" class="form-label">Branch *</label>
                <AppSelect
                    :input-id="`${modalId}_branch`"
                    v-model="form.branch_id"
                    :options="branchSelectOptions"
                    :placeholder="branchesLoading ? 'Loading branches…' : 'Select branch'"
                    search-placeholder="Search branch…"
                    :disabled="!form.tenant_id || branchesLoading"
                    :invalid="Boolean(fieldErrors.branch_id)"
                    :error-message="fieldErrors.branch_id?.[0] ?? ''"
                />
                <div
                    v-if="form.tenant_id && !branchesLoading && companyBranches.length === 0"
                    class="form-text text-danger"
                >
                    No branches for this company. Create a branch first.
                </div>
            </div>
        </div>
        <div v-if="showDocumentAlertPreferences" class="border rounded p-3 mb-3 bg-light">
            <h6 class="fw-bold mb-2">
                <i class="fas fa-bell me-2 text-brand-primary"></i>
                Document Alert Notifications
            </h6>
            <p class="text-muted small mb-3">
                Choose how this user receives document expiry alerts for their company.
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input
                            :id="`${modalId}_document_alerts_live`"
                            v-model="form.document_alerts_live"
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                        />
                        <label class="form-check-label" :for="`${modalId}_document_alerts_live`">
                            Live notifications
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input
                            :id="`${modalId}_document_alerts_email`"
                            v-model="form.document_alerts_email"
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                        />
                        <label class="form-check-label" :for="`${modalId}_document_alerts_email`">
                            Email notifications
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label :for="`${modalId}_password`" class="form-label">
                    {{ isEdit ? 'New Password' : 'Password *' }}
                </label>
                <input
                    :id="`${modalId}_password`"
                    v-model="form.password"
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': fieldErrors.password }"
                    :required="!isEdit"
                />
                <div v-if="fieldErrors.password" class="invalid-feedback d-block">
                    {{ fieldErrors.password[0] }}
                </div>
            </div>
            <div class="col-md-6">
                <label :for="`${modalId}_password_confirmation`" class="form-label">
                    Confirm Password {{ isEdit ? '' : '*' }}
                </label>
                <input
                    :id="`${modalId}_password_confirmation`"
                    v-model="form.password_confirmation"
                    type="password"
                    class="form-control"
                    :required="!isEdit && !!form.password"
                />
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="close">Cancel</button>
            <button type="submit" class="btn" :class="isEdit ? 'btn-warning' : 'btn-success'" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                {{ isEdit ? 'Update' : 'Create' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { listAllBranches } from '../../api/branches';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';

const SUPER_ADMIN_ROLE_ID = 2;
const MASTER_ADMIN_ROLE_ID = 1;
const BRANCH_REQUIRED_ROLE_IDS = [3, 4, 5];

const props = defineProps({
    modalId: { type: String, default: 'userFormModal' },
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    user: { type: Object, default: null },
    assignableRoles: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

const emit = defineEmits(['close', 'submit']);

const isEdit = computed(() => props.mode === 'edit');
const isProtectedUser = computed(() => props.user?.is_protected === true);

const requiresCompany = computed(() => {
    const roleId = Number(form.value.user_role_id);

    return roleId > 0 && roleId !== SUPER_ADMIN_ROLE_ID && roleId !== MASTER_ADMIN_ROLE_ID;
});

const requiresBranch = computed(() => {
    const roleId = Number(form.value.user_role_id);

    return BRANCH_REQUIRED_ROLE_IDS.includes(roleId);
});

const branches = ref([]);
const branchesLoading = ref(false);

const companyBranches = computed(() => {
    if (!form.value.tenant_id) {
        return [];
    }

    return branches.value.filter((branch) => branch.company_id === form.value.tenant_id);
});

const roleSelectOptions = computed(() =>
    props.assignableRoles.map((role) => ({
        value: role.id,
        label: role.label,
    })),
);

const companySelectOptions = computed(() =>
    props.companies.map((company) => ({
        value: company.id,
        label: company.name,
    })),
);

const branchSelectOptions = computed(() =>
    companyBranches.value.map((branch) => ({
        value: branch.id,
        label: branch.branch_name,
    })),
);

const showDocumentAlertPreferences = computed(
    () => requiresCompany.value || Boolean(props.user?.tenant_id),
);

const form = ref({
    name: '',
    email: '',
    user_role_id: '',
    tenant_id: '',
    branch_id: '',
    document_alerts_live: false,
    document_alerts_email: false,
    password: '',
    password_confirmation: '',
});

async function loadBranches() {
    branchesLoading.value = true;

    try {
        branches.value = await listAllBranches();
    } catch {
        branches.value = [];
    } finally {
        branchesLoading.value = false;
    }
}

function resetForm() {
    if (props.user) {
        form.value = {
            name: props.user.name ?? '',
            email: props.user.email ?? '',
            user_role_id: props.user.user_role_id ?? '',
            tenant_id: props.user.tenant_id ?? '',
            branch_id: props.user.branch_id ?? '',
            document_alerts_live: Boolean(props.user.document_alerts_live),
            document_alerts_email: Boolean(props.user.document_alerts_email),
            password: '',
            password_confirmation: '',
        };
    } else {
        form.value = {
            name: '',
            email: '',
            user_role_id: '',
            tenant_id: '',
            branch_id: '',
            document_alerts_live: false,
            document_alerts_email: false,
            password: '',
            password_confirmation: '',
        };
    }
}

function close() {
    emit('close');
}

function submit() {
    const payload = {
        name: form.value.name,
        email: form.value.email,
        user_role_id: Number(form.value.user_role_id),
    };

    if (requiresCompany.value) {
        payload.tenant_id = form.value.tenant_id;
        payload.document_alerts_live = form.value.document_alerts_live;
        payload.document_alerts_email = form.value.document_alerts_email;
    }

    if (requiresBranch.value) {
        payload.branch_id = Number(form.value.branch_id);
    }

    if (form.value.password) {
        payload.password = form.value.password;
        payload.password_confirmation = form.value.password_confirmation;
    }

    emit('submit', payload);
}

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            resetForm();
            loadBranches();
        }
    },
);

watch(
    () => props.user,
    () => {
        if (props.show) {
            resetForm();
        }
    },
);

watch(
    () => form.value.tenant_id,
    (tenantId, previousTenantId) => {
        if (tenantId !== previousTenantId) {
            form.value.branch_id = '';
        }
    },
);

watch(
    () => form.value.user_role_id,
    (roleId) => {
        if (!BRANCH_REQUIRED_ROLE_IDS.includes(Number(roleId))) {
            form.value.branch_id = '';
        }
    },
);
</script>

<style scoped>
@media (max-width: 767.98px) {
    .row.mb-3,
    .mb-3 {
        margin-bottom: 0.5rem !important;
    }

    .row {
        --bs-gutter-x: 0.5rem !important;
        --bs-gutter-y: 0.4rem !important;
    }

    .form-label,
    :deep(.form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    .form-control,
    :deep(.form-control),
    :deep(.select-trigger) {
        height: 32px !important;
        min-height: 32px !important;
        font-size: 0.75rem !important;
        padding: 0.25rem 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.select-trigger-label),
    :deep(.select-trigger-placeholder) {
        font-size: 0.75rem !important;
    }

    .invalid-feedback,
    :deep(.invalid-feedback),
    .form-text,
    :deep(.form-text) {
        font-size: 0.68rem !important;
        margin-top: 0.15rem !important;
    }

    .border.rounded.p-3 {
        padding: 0.5rem !important;
        margin-bottom: 0.5rem !important;
    }

    .border.rounded h6 {
        font-size: 0.78rem !important;
        margin-bottom: 0.2rem !important;
    }

    .border.rounded p {
        font-size: 0.68rem !important;
        margin-bottom: 0.35rem !important;
    }

    .form-check {
        margin-bottom: 0.35rem !important;
    }

    .form-check-label,
    :deep(.form-check-label) {
        font-size: 0.72rem !important;
        line-height: 1.3 !important;
    }

    .form-check-input,
    :deep(.form-check-input) {
        width: 0.9em !important;
        height: 0.9em !important;
        margin-top: 0.15em !important;
    }

    .btn,
    :deep(.btn) {
        font-size: 0.72rem !important;
        height: 28px !important;
        min-height: 28px !important;
        padding: 0.15rem 0.6rem !important;
        border-radius: 5px !important;
    }
}
</style>
