<template>
    <div class="container mt-4 app-index-page">
        <AppIndexPageHeader
            title="Company Management"
            
        >
            <template v-if="canCreate" #actions>
                <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
            </template>
        </AppIndexPageHeader>

        <div v-if="routeNotice" class="alert alert-warning">{{ routeNotice }}</div>

        <div class="card app-index-list-card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Companies List</h3>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading companies…</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px">#</th>
                                <th>Name</th>
                                <th>GST Number</th>
                                <th>PAN Number</th>
                                <th>Contact</th>
                                <th>Plan End</th>
                                <th class="app-index-actions-cell" style="width: 240px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="companies.length === 0">
                                <td colspan="7" class="text-center text-muted py-4">
                                    <span v-if="canCreate">
                                        No companies yet. Use the Create button to register your first company.
                                    </span>
                                    <span v-else>No companies yet.</span>
                                </td>
                            </tr>
                            <tr v-for="(company, index) in companies" :key="company.id">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    {{ company.name }}
                                    <span v-if="company.is_blocked" class="badge bg-danger ms-1">Blocked</span>
                                    <span
                                        v-else-if="isActiveCompany(company.id)"
                                        class="badge bg-success ms-1"
                                    >Active</span>
                                </td>
                                <td>{{ company.gst_number || '—' }}</td>
                                <td>{{ company.pan_number || '—' }}</td>
                                <td>
                                    <span v-if="company.contact_person">{{ company.contact_person }}</span>
                                    <small v-if="company.contact_number" class="d-block text-muted">{{
                                        company.contact_number
                                    }}</small>
                                    <span v-if="!company.contact_person && !company.contact_number">—</span>
                                </td>
                                <td>{{ formatDate(company.plan_end_date) }}</td>
                                <td class="app-index-actions-cell text-nowrap">
                                    <AppButton
                                        v-if="canUpdate"
                                        size="sm"
                                        variant="secondary"
                                        label="Edit"
                                        class="me-1"
                                        @click="openEdit(company)"
                                    />
                                    <AppButton
                                        v-if="canBlock"
                                        size="sm"
                                        :variant="company.is_blocked ? 'success' : 'warning'"
                                        :label="company.is_blocked ? 'Unblock' : 'Block'"
                                        @click="toggleBlock(company)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <CompanyFormModal
            :show="showForm"
            :mode="formMode"
            :company="editingCompany"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="companyFormError"
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
import { computed, inject, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import {
    createCompany,
    listAllCompanies,
    updateCompany,
} from '../../api/companies';
import CompanyFormModal from '../../components/companies/CompanyFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { formatDate } from '../../utils/datetime';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { buildCompanyFields } from '../../utils/masterCrudSaveConfirm';

const router = useRouter();
const route = useRoute();
const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const currentUser = inject('currentUser', ref(null));
const { can } = usePermissions();

const companies = ref([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const formMode = ref('create');
const editingCompany = ref(null);
const fieldErrors = ref({});
const companyFormError = ref('');

const canCreate = computed(() => can('companies.create'));
const canUpdate = computed(() => can('companies.update'));
const canBlock = computed(() => can('companies.block'));

const activeCompanyId = computed(
    () => currentUser.value?.effective_company_id ?? currentUser.value?.company_id ?? null,
);

const routeNotice = computed(() => route.query.notice ?? '');

function isActiveCompany(id) {
    return activeCompanyId.value === id;
}

async function refreshUser() {
    const user = await fetchCurrentUser();
    currentUser.value = user;
    window.__AUTH_USER__ = user;
}

async function loadCompanies() {
    loading.value = true;

    try {
        companies.value = await listAllCompanies();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Failed to load companies.');
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    formMode.value = 'create';
    editingCompany.value = null;
    fieldErrors.value = {};
    companyFormError.value = '';
    showForm.value = true;
}

function openEdit(company) {
    formMode.value = 'edit';
    editingCompany.value = company;
    fieldErrors.value = {};
    companyFormError.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    formMode.value = 'create';
    editingCompany.value = null;
    fieldErrors.value = {};
    companyFormError.value = '';
}

function handleSubmit(payload) {
    fieldErrors.value = {};
    companyFormError.value = '';

    promptSaveConfirm({
        payload,
        entityLabel: 'Company',
        mode: formMode.value === 'edit' ? 'edit' : 'create',
        fields: buildCompanyFields(payload, editingCompany.value),
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;
    fieldErrors.value = {};
    companyFormError.value = '';

    try {
        if (formMode.value === 'edit' && editingCompany.value) {
            await updateCompany(editingCompany.value.id, payload);
            toast.success('Company updated successfully.');
        } else {
            await createCompany(payload);
            toast.success('Company created successfully.');
        }

        finishSaveConfirm();
        closeForm();
        await loadCompanies();
    } catch (error) {
        finishSaveConfirm();

        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
            companyFormError.value =
                error.response.data.message ?? 'Please check the highlighted fields.';

            return;
        }

        companyFormError.value =
            error.response?.data?.message ??
            (formMode.value === 'edit' ? 'Could not update company.' : 'Could not create company.');
    } finally {
        saving.value = false;
    }
}

async function toggleBlock(company) {
    try {
        await updateCompany(company.id, { is_blocked: !company.is_blocked });
        toast.success(company.is_blocked ? 'Company unblocked.' : 'Company blocked.');
        await loadCompanies();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not update company.');
    }
}

onMounted(loadCompanies);
</script>
