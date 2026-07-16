<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="routeNotice" class="alert alert-warning">{{ routeNotice }}</div>

        <AppIndexListCard title="Company Management" :loading="loading" loading-text="Loading companies…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Create company"
                    @click="openCreate"
                >
                    Create
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="companies-filters-panel"
                :loading="loading"
                @search="loadCompanies(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="companiesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="filters.search"
                        input-id="companiesIndex_search"
                        placeholder="Search name, GST, PAN…"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadCompanies(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px" class="text-center">S.No.</th>
                        <th>Company Name</th>
                        <th>GST Number</th>
                        <th>PAN Number</th>
                        <th>Contact</th>
                        <th>Plan End</th>
                        <th class="app-index-actions-cell" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="companies.length === 0" class="app-index-empty-row">
                        <td colspan="7" class="text-center text-muted py-4">
                            {{ filters.search ? 'No companies found for selected filters.' : 'No companies found. Create your first company.' }}
                        </td>
                    </tr>
                    <tr v-for="(company, index) in companies" :key="company.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'companies.show', params: { id: company.id } }"
                                class="company-name-link"
                                title="View company details"
                            >
                                {{ company.name }}
                            </RouterLink>
                            <span
                                v-if="isActiveCompany(company.id)"
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
                        <td class="app-index-actions-cell">
                           <AppTableActions>
                               <!--  <AppCrudActionButton
                                    action="view"
                                    @click="goToDetail(company)"
                                />-->
                                <AppCrudActionButton
                                    v-if="canUpdate"
                                    action="edit"
                                    @click="openEdit(company)"
                                />
                            </AppTableActions>
                        </td>
                    </tr>
                </tbody>
            </table>

            <template #footer>
                <AppIndexPartloadPagination
                    :pagination="pagination"
                    :loading="loading"
                    item-label="companies"
                    aria-label="Companies pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

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
import { computed, inject, onMounted, ref, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fetchCurrentUser } from '../../api';
import {
    createCompany,
    listCompanies,
    updateCompany,
} from '../../api/companies';
import CompanyFormModal from '../../components/companies/CompanyFormModal.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import { formatDate } from '../../utils/datetime';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useAppToast } from '../../composables/useAppToast';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { useLocationLookups } from '../../composables/useLocationLookups';
import { buildCompanyFields } from '../../utils/masterCrudSaveConfirm';

const route = useRoute();
const router = useRouter();
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
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const { states, initialize: initializeLocations } = useLocationLookups();

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

const activeCompanyId = computed(
    () => currentUser.value?.effective_company_id ?? currentUser.value?.company_id ?? null,
);

const routeNotice = computed(() => route.query.notice ?? '');

const filters = reactive({
    search: '',
});

function clearFilters() {
    filters.search = '';
    loadCompanies(1);
}

function isActiveCompany(id) {
    return activeCompanyId.value === id;
}

function rowNumber(index) {
    return (pagination.currentPage - 1) * pagination.perPage + index + 1;
}

function goToDetail(company) {
    router.push({ name: 'companies.show', params: { id: company.id } });
}

async function loadCompanies(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listCompanies({
            page,
            perPage: pagination.perPage,
            search: filters.search.trim(),
        });
        companies.value = response.data;
        syncFromMeta(response.meta, companies.value.length);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Failed to load companies.');
        companies.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadCompanies(page);
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
        fields: buildCompanyFields(payload, editingCompany.value, { states: states.value }),
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

onMounted(async () => {
    await initializeLocations();
    await loadCompanies();
});
</script>

<style scoped>
.company-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.company-name-link:hover {
    text-decoration: underline;
}

/* Custom filters single row layout */
:deep(.app-index-collapsible-filters__inner) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
}

:deep(.app-index-filters-fields) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    flex: 1 1 auto;
}

:deep(.app-index-collapsible-filters__actions) {
    margin-top: 0;
    align-self: flex-end;
}

.filter-search-col {
    flex: 0 0 280px;
    width: 280px;
}

.filter-select-col {
    flex: 0 0 180px;
    width: 180px;
}
</style>
