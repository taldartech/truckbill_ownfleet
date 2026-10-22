<template>
    <div class="container mt-4 branch-detail-page">
        <div v-if="loading" class="text-center py-5 text-muted">Loading branch details…</div>

        <template v-else-if="branch">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Branch Information ({{ branch.branch_name }})</strong>
                            <div class="app-index-table-actions ms-auto" style="margin-left: auto !important; display: inline-flex !important; gap: 0.375rem !important;">
                                 <RouterLink
                                    :to="{ name: 'branches.index' }"
                                    class="btn btn-sm btn-outline-secondary btn-icon app-index-action-btn"
                                    title="Back to Branches"
                                    style="background-color: #fff; border-color: #cbd5e1; color: #475569;"
                                >
                                    <i class="fas fa-arrow-left" style="color: #475569 !important;" aria-hidden="true"></i>
                                </RouterLink>
                                <AppCrudActionButton
                                    v-if="canManage"
                                    action="edit"
                                    @click="openEdit"
                                />
                                <AppCrudActionButton
                                    v-if="canManage"
                                    action="delete"
                                    @click="handleDelete(branch)"
                                />
                            </div>
                        </div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Branch Name</span>
                                <div class="fw-semibold">{{ branch.branch_name }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Company</span>
                                <div>{{ branch.company?.name || branch.company_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Contact Number</span>
                                <div>{{ branch.branch_contact_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Head Office</span>
                                <div>{{ branch.head_office ? 'Yes' : 'No' }}</div>
                            </div>
                            <div class="col-md-8">
                                <span class="text-muted">Address</span>
                                <div>{{ branch.branch_address || '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>GST Settings</strong></div>
                        <div class="card-body row g-3 small">
                            <div class="col-md-4">
                                <span class="text-muted">Inherit Company GST</span>
                                <div>{{ branch.inherit_company_gst ? 'Yes' : 'No' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">GST Number</span>
                                <div class="fw-semibold">{{ branch.gst_number || branch.effective_gst?.gst_number || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Registration Type</span>
                                <div>{{ branch.registration_type || branch.effective_gst?.registration_type || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Tax Liability</span>
                                <div>{{ branch.tax_liability || branch.effective_gst?.tax_liability || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">State</span>
                                <div>{{ branch.state_name || branch.company?.state_name || '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">GST Rate</span>
                                <div>{{ branch.gst_rate !== null ? branch.gst_rate + '%' : branch.effective_gst?.gst_rate !== null && branch.effective_gst?.gst_rate !== undefined ? branch.effective_gst.gst_rate + '%' : '—' }}</div>
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
                                <div>{{ formatDateTime(branch.created_at) }}</div>
                                <div v-if="branch.created_by_name" class="text-muted">
                                    by {{ branch.created_by_name }}
                                </div>
                                <div v-else class="text-muted">by —</div>
                            </div>
                            <div>
                                <span class="text-muted">Last Updated</span>
                                <div>{{ formatDateTime(branch.updated_at) }}</div>
                                <div v-if="branch.updated_by_name" class="text-muted">
                                    by {{ branch.updated_by_name }}
                                </div>
                                <div v-else class="text-muted">by —</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="alert alert-danger">
            Branch not found or you do not have access.
        </div>

        <BranchFormModal
            :show="showForm"
            mode="edit"
            :branch="branch"
            :companies="companies"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="branchFormError"
            @close="closeForm"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import { fetchBranch, updateBranch, deleteBranch, fetchBranchDeletionImpact } from '../../api/branches';
import { listAllCompanies } from '../../api/companies';
import BranchFormModal from '../../components/branches/BranchFormModal.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { can } = usePermissions();
const currentUser = inject('currentUser', ref(null));

const canManage = computed(() => can('branches.manage'));

const activeBranchId = computed(
    () => currentUser.value?.effective_branch_id ?? currentUser.value?.branch_id ?? null,
);

function isActiveBranch(id) {
    return Number(activeBranchId.value) === Number(id);
}

const branch = ref(null);
const companies = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const { fieldErrors, formError: branchFormError, clearErrors } = useApiFormErrors();

async function loadBranch() {
    const id = route.params.id;
    if (!id) {
        branch.value = null;
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        branch.value = await fetchBranch(id);
    } catch {
        branch.value = null;
        toast.error('Could not load branch details.');
    } finally {
        loading.value = false;
    }
}

async function loadCompanies() {
    try {
        companies.value = await listAllCompanies();
    } catch {
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
    if (!branch.value?.id) {
        return;
    }

    saving.value = true;
    clearErrors();

    try {
        branch.value = await updateBranch(branch.value.id, payload);
        toast.success('Branch updated successfully.');
        closeForm();
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
            branchFormError.value =
                error.response.data.message ?? 'Please check the highlighted fields.';
        } else {
            toast.error('Unable to save branch.');
        }
    } finally {
        saving.value = false;
    }
}

async function handleDelete(branchVal) {
    const targetBranch = branchVal || branch.value;
    if (!targetBranch?.id) {
        return;
    }

    let impact;

    try {
        impact = await fetchBranchDeletionImpact(targetBranch.id);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not load branch delete details.');

        return;
    }

    if (!window.confirm(buildBranchDeleteMessage(targetBranch.branch_name, impact))) {
        return;
    }

    try {
        const data = await deleteBranch(targetBranch.id);

        if (data?.user) {
            currentUser.value = data.user;
            window.__AUTH_USER__ = data.user;
        } else if (isActiveBranch(targetBranch.id)) {
            const user = await fetchCurrentUser();
            currentUser.value = user;
            window.__AUTH_USER__ = user;
        }

        toast.success('Branch deleted successfully.');
        router.push({ name: 'branches.index' });
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
    await Promise.all([loadBranch(), loadCompanies()]);
});

watch(() => route.params.id, () => {
    loadBranch();
});
</script>

<style scoped>
@media (max-width: 767.98px) {
    .branch-detail-page {
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

    .card-body .col-md-12 {
        flex: 0 0 100% !important;
        max-width: 100% !important;
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
