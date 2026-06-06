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
        <div v-if="flash.success" class="alert alert-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger">{{ flash.error }}</div>

        <div class="card app-index-list-card mb-4">
            <div class="card-header">
                <h3 class="card-title mb-0">Companies List</h3>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading companies…</div>
                <div v-else class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 60px">#</th>
                                <th>Name</th>
                                <th>GST Number</th>
                                <th>PAN Number</th>
                                <th>Contact</th>
                                <th>Plan End</th>
                                <th>LR Credits</th>
                                <th style="width: 180px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="companies.length === 0">
                                <td colspan="8" class="text-center text-muted py-4">
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
                                <td>{{ company.plan_end_date_formatted || '—' }}</td>
                                <td>{{ company.lr_credits ?? '—' }}</td>
                                <td class="text-nowrap">
                                    <AppButton
                                        v-if="canEnter"
                                        size="sm"
                                        label="Enter"
                                        class="me-1"
                                        :disabled="company.is_blocked"
                                        @click="handleEnter(company)"
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
            :show="showCreate"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="companyFormError"
            @close="closeCreate"
            @submit="handleCreate"
        />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import {
    createCompany,
    enterCompany,
    listAllCompanies,
    updateCompany,
} from '../../api/companies';
import CompanyFormModal from '../../components/companies/CompanyFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIndexPageHeader from '../../components/ui/AppIndexPageHeader.vue';
import { usePermissions } from '../../composables/usePermissions';

const router = useRouter();
const route = useRoute();
const currentUser = inject('currentUser', ref(null));
const { can } = usePermissions();

const companies = ref([]);
const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const fieldErrors = ref({});
const companyFormError = ref('');
const flash = reactive({ success: '', error: '' });

const canCreate = computed(() => can('companies.create'));
const canEnter = computed(() => can('companies.enter'));
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
    flash.error = '';

    try {
        companies.value = await listAllCompanies();
    } catch (error) {
        flash.error = error.response?.data?.message ?? 'Failed to load companies.';
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    fieldErrors.value = {};
    companyFormError.value = '';
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    fieldErrors.value = {};
    companyFormError.value = '';
}

async function handleCreate(payload) {
    saving.value = true;
    fieldErrors.value = {};
    companyFormError.value = '';

    try {
        await createCompany(payload);
        closeCreate();
        flash.success = 'Company created successfully.';
        await loadCompanies();
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
            companyFormError.value =
                error.response.data.message ?? 'Please check the highlighted fields.';

            return;
        }

        companyFormError.value = error.response?.data?.message ?? 'Could not create company.';
    } finally {
        saving.value = false;
    }
}

async function handleEnter(company) {
    flash.error = '';

    try {
        const data = await enterCompany(company.id);
        if (data?.user) {
            currentUser.value = data.user;
            window.__AUTH_USER__ = data.user;
        } else {
            await refreshUser();
        }
        flash.success = `Entered ${company.name}.`;
        await router.push({ name: 'dashboard' });
    } catch (error) {
        flash.error = error.response?.data?.message ?? 'Could not enter company.';
    }
}

async function toggleBlock(company) {
    flash.error = '';

    try {
        await updateCompany(company.id, { is_blocked: !company.is_blocked });
        flash.success = company.is_blocked ? 'Company unblocked.' : 'Company blocked.';
        await loadCompanies();
    } catch (error) {
        flash.error = error.response?.data?.message ?? 'Could not update company.';
    }
}

onMounted(loadCompanies);
</script>
