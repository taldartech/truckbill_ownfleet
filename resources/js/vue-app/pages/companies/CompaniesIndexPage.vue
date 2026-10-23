<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="routeNotice" class="alert alert-warning">{{ routeNotice }}</div>

        <AppIndexListCard title="Company Management" :loading="loading" loading-text="Loading companies…">
            <template #toolbar>
                <button
                    v-if="canCreate"
                    type="button"
                    class="btn btn-sm bg-white text-success shadow-sm d-inline-flex align-items-center justify-content-center fw-semibold rounded-2 p-0 px-md-3 py-md-1"
                    style="min-width: 32px; height: 32px;"
                    title="Create Company"
                    aria-label="Create company"
                    @click="openCreate"
                >
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
                    </svg>
                    <span class="d-none d-md-inline ms-1"><span class="d-none d-lg-inline">Create </span>Company</span>
                </button>
            </template>

            <template #filters>
                <div class="d-flex d-md-none align-items-center justify-content-between px-3 py-2 border-bottom bg-white">
                    <span class="fw-bold text-uppercase text-dark small text-nowrap" style="letter-spacing: 0.05em; font-size: 0.8125rem; white-space: nowrap;">SEARCH &amp; FILTERS</span>
                    <button
                        type="button"
                        class="btn btn-sm rounded-pill border bg-white text-dark shadow-sm d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold"
                        style="font-size: 0.8125rem;"
                        :class="{ 'bg-light border-secondary': filtersOpen }"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>
                        <span>Filters</span>
                        <i
                            class="bi ms-1 text-muted"
                            :class="filtersOpen ? 'bi-chevron-up' : 'bi-chevron-down'"
                            style="font-size: 0.75rem;"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>

                <div v-show="filtersOpen" class="companies-filters-content">
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
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px" class="text-center text-nowrap">S.No.</th>
                        <th class="text-nowrap">Company Name</th>
                        <th class="text-nowrap">GST Number</th>
                        <th class="text-nowrap">PAN Number</th>
                        <th class="text-nowrap">Contact</th>
                        <th class="text-nowrap">Plan End</th>
                        <th class="app-index-actions-cell text-nowrap" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="companies.length === 0" class="app-index-empty-row">
                        <td colspan="7" class="text-center text-muted py-4">
                            {{ filters.search ? 'No companies found for selected filters.' : 'No companies found. Create your first company.' }}
                        </td>
                    </tr>
                    <tr v-for="(company, index) in companies" :key="company.id">
                        <td class="text-center text-nowrap">{{ rowNumber(index) }}</td>
                        <td class="text-nowrap">
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
                        <td class="text-nowrap">{{ company.gst_number || '—' }}</td>
                        <td class="text-nowrap">{{ company.pan_number || '—' }}</td>
                        <td class="text-nowrap">
                            <span v-if="company.contact_person">{{ company.contact_person }}</span>
                            <small v-if="company.contact_number" class="d-block text-muted">{{
                                company.contact_number
                            }}</small>
                            <span v-if="!company.contact_person && !company.contact_number">—</span>
                        </td>
                        <td class="text-nowrap">{{ formatDate(company.plan_end_date) }}</td>
                        <td class="app-index-actions-cell text-nowrap">
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
const filtersOpen = ref(false);

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

@media (max-width: 767.98px) {
    :deep(.card-header) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 0.65rem 0.85rem !important;
    }

    :deep(.card-title) {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        margin-bottom: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    :deep(.app-index-card-toolbar) {
        width: auto !important;
        flex: 0 0 auto !important;
        margin-left: auto !important;
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 0.35rem !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: flex-end !important;
        gap: 6px !important;
        width: 100% !important;
        padding: 0.5rem 0.65rem !important;
    }

    :deep(.app-index-filters-fields) {
        display: flex !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        width: auto !important;
    }

    .filter-search-col,
    :deep(.filter-search-col),
    :deep(.app-index-filters-fields > div) {
        flex: 1 1 auto !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        margin: 0 !important;
    }

    :deep(.app-index-search) {
        width: 100% !important;
        max-width: 100% !important;
    }

    :deep(.app-index-collapsible-filters .form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
        white-space: nowrap !important;
    }

    :deep(.app-index-collapsible-filters .form-control) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
        width: 100% !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder) {
        font-size: 0.75rem !important;
    }

    /* Search and Reset buttons in same line with input box on mobile (icon-only) */
    :deep(.app-index-collapsible-filters__actions) {
        width: auto !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 5px !important;
        margin-top: 0 !important;
        margin-left: 0 !important;
        margin-bottom: 0 !important;
        align-self: flex-end !important;
        flex-shrink: 0 !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        flex: 0 0 34px !important;
        width: 34px !important;
        min-width: 34px !important;
        max-width: 34px !important;
        height: 33px !important;
        min-height: 33px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 6px !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn .app-index-filter-btn-text),
    :deep(.app-index-collapsible-filters__btn .app-index-filter-btn-text) {
        display: none !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn i),
    :deep(.app-index-collapsible-filters__btn i) {
        font-size: 0.85rem !important;
        margin: 0 !important;
    }

    /* Table micro typography and padding on mobile */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    .table {
        font-size: 0.72rem !important;
    }

    .table th {
        font-size: 0.72rem !important;
        padding: 0.4rem 0.35rem !important;
        white-space: nowrap !important;
    }

    .table td {
        font-size: 0.72rem !important;
        padding: 0.35rem 0.35rem !important;
        vertical-align: middle !important;
        white-space: nowrap !important;
    }

    .company-name-link {
        font-size: 0.72rem !important;
    }

    .badge {
        font-size: 0.65rem !important;
        padding: 0.2rem 0.35rem !important;
    }

    :deep(.app-table-actions) {
        gap: 3px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-wrap: nowrap !important;
    }

    :deep(.app-table-actions .btn),
    :deep(.app-table-actions button) {
        height: 24px !important;
        min-height: 24px !important;
        font-size: 0.68rem !important;
        padding: 0.1rem 0.45rem !important;
        line-height: 1 !important;
        border-radius: 4px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    :deep(.app-crud-action-button),
    :deep(.app-index-action-btn) {
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
        min-height: 24px !important;
        font-size: 0.65rem !important;
        padding: 0 !important;
        border-radius: 4px !important;
    }

    :deep(.app-crud-action-button i),
    :deep(.app-index-action-btn i),
    :deep(.app-index-action-btn .fas) {
        font-size: 0.625rem !important;
    }

    /* Pagination centered in middle on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.45rem !important;
        padding: 0.6rem 0.6rem !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.75rem !important;
        text-align: center !important;
        width: 100% !important;
        margin: 0 auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: auto !important;
        margin-right: auto !important;
        display: flex !important;
        justify-content: center !important;
        width: 100% !important;
    }

    :deep(.app-index-partload-pagination__list) {
        margin-left: auto !important;
        margin-right: auto !important;
        justify-content: center !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.22rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

@media (max-width: 380px) {
    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0.7rem !important;
        height: 26px !important;
        min-height: 26px !important;
    }
}
</style>
