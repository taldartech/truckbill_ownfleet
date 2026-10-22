<template>
    <div class="container mt-4 app-index-page app-index-page--standard app-index-page--inline-filters customers-index-page">
        <AppIndexListCard title="Customers" :loading="loading" loading-text="Loading customers…">
            <template #toolbar>
                <button
                    type="button"
                    class="btn btn-sm bg-white shadow-sm d-inline-flex align-items-center justify-content-center gap-1 customer-header-btn customer-header-btn--filter"
                    title="Filters"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    <svg class="filter-slider-icon" width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM9.05 3a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM2.05 8a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1H6.95a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h1.05zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-2.45 1a2.5 2.5 0 0 1 4.9 0H15a.5.5 0 0 1 0 1h-1.05a2.5 2.5 0 0 1-4.9 0H1a.5.5 0 0 1 0-1h8.05z"/>
                    </svg>
                    <span class="filter-btn-text d-none d-md-inline ms-1">Filters</span>
                    <span v-if="filtersActive" class="badge bg-success text-white rounded-pill ms-1 d-none d-md-inline" style="font-size: 0.65rem;">Active</span>
                    <svg class="filter-chevron-icon ms-1 d-none d-md-inline" width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path v-if="filtersOpen" fill-rule="evenodd" d="M7.646 4.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1-.708.708L8 5.707l-5.646 5.647a.5.5 0 0 1-.708-.708l6-6z"/>
                        <path v-else fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="upload"
                    class="customer-header-btn customer-header-btn--upload"
                    title="Bulk Upload Customers"
                    aria-label="Bulk Upload Customers"
                    @click="showBulkUpload = true"
                >
                    <span class="customer-btn-label d-none d-md-inline">Bulk Upload</span>
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    class="customer-header-btn customer-header-btn--add"
                    title="Add Customer"
                    aria-label="Add Customer"
                    @click="openCreate"
                >
                    <span class="customer-btn-label d-none d-md-inline">Add Customer</span>
                </AppIndexHeaderButton>
            </template>

            <template #filters>
                <div v-show="!isMobile || filtersOpen" class="customers-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="customers-filters-panel"
                        :loading="loading"
                        @search="loadCustomers(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col customers-search-col">
                            <label class="form-label d-none d-md-block" for="customersIndex_search">Search</label>
                            <AppIndexSearchInput
                                v-model="search"
                                input-id="customersIndex_search"
                                placeholder="Search name, GST, PAN, contact"
                                max-width="100%"
                                size="md"
                                class="customers-search-input"
                                :disabled="loading"
                                @keydown.enter="loadCustomers(1)"
                            />
                        </div>
                    </AppIndexCollapsibleFilters>
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px">S.No.</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>GST</th>
                        <th>PAN</th>
                        <th>City</th>
                        <th>State</th>
                        <th v-if="canEdit" class="app-index-actions-cell text-end" style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="customers.length === 0" class="app-index-empty-row">
                        <td :colspan="canEdit ? 8 : 7" class="text-center text-muted py-4">No customers found.</td>
                    </tr>
                    <tr v-for="(customer, index) in customers" :key="customer.id">
                        <td>{{ (pagination.currentPage - 1) * pagination.perPage + index + 1 }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'customers.show', params: { id: customer.id } }"
                                class="customer-name-link"
                                title="View customer details"
                            >
                                {{ customer.name }}
                            </RouterLink>
                        </td>
                        <td>{{ customer.contact_number || '—' }}</td>
                        <td>{{ customer.gst_number || '—' }}</td>
                        <td>{{ customer.pan_number || '—' }}</td>
                        <td>{{ customer.city_name || '—' }}</td>
                        <td>{{ customer.state_name || '—' }}</td>
                        <td v-if="canEdit" class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    action="edit"
                                    @click="openEdit(customer)"
                                />
                                <AppCrudActionButton
                                    action="delete"
                                    @click="handleDelete(customer)"
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
                    aria-label="Customers pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <CustomerFormModal
            :show="showModal"
            :saving="saving"
            :customer="editing"
            :field-errors="fieldErrors"
            :form-error="formError"
            @close="closeModal"
            @submit="handleSubmit"
        />

        <MasterCrudSaveConfirmModal
            :show="showSaveConfirm"
            :summary="saveConfirmSummary"
            :saving="saving"
            @cancel="cancelSaveConfirm"
            @confirm="confirmSave"
        />

        <BulkUploadModal
            v-model:show="showBulkUpload"
            title="Bulk Upload Customers"
            upload-url="/app/api/customers/bulk-upload"
            template-download-url="/app/api/customers/bulk-upload/template"
            template-file-name="customers_bulk_upload_template.xlsx"
            :columns="bulkUploadColumns"
            @success="loadCustomers(1)"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { createCustomer, deleteCustomer, listCustomers, updateCustomer } from '../../api/customers';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import BulkUploadModal from '../../components/ui/BulkUploadModal.vue';
import CustomerFormModal from '../../components/customers/CustomerFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';

const toast = useAppToast();
const {
    showSaveConfirm,
    saveConfirmSummary,
    pendingPayload,
    promptSaveConfirm,
    cancelSaveConfirm,
    finishSaveConfirm,
} = useMasterCrudSaveConfirm();
const { canInCompany, canCreateInCompany, canEditInCompany } = usePermissions();
const canCreate = computed(() => canCreateInCompany('customers') || canInCompany('trips.create'));
const canEdit = computed(() => canEditInCompany('customers'));

const customers = ref([]);
const loading = ref(true);
const saving = ref(false);
const search = ref('');
const isMobile = ref(false);
const filtersOpen = ref(false);
const filtersActive = computed(() => search.value.trim() !== '');
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const showModal = ref(false);
const editing = ref(null);
const showBulkUpload = ref(false);

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function handleResize() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
}

const bulkUploadColumns = [
    { name: 'name', label: 'Customer Name', required: true, description: 'Customer / company name' },
    { name: 'contact_number', label: 'Mobile', required: true, description: 'Unique 10-digit mobile number' },
    { name: 'state', label: 'State', required: true, description: 'Registered State name or code' },
    { name: 'city', label: 'City', required: true, description: 'City belonging to the selected State' },
    { name: 'address', label: 'Address', required: false, description: 'Full address' },
    { name: 'gst_number', label: 'GST Number', required: false, description: '15-character GSTIN' },
    { name: 'pan_number', label: 'PAN Number', required: false, description: '10-character PAN' },
];

const { fieldErrors, formError, clearErrors, applyApiError } = useApiFormErrors();

function clearFilters() {
    search.value = '';
    pagination.currentPage = 1;
    loadCustomers(1);
}

async function loadCustomers(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listCustomers({
            page,
            perPage: pagination.perPage,
            search: search.value.trim(),
        });

        customers.value = response.data;
        syncFromMeta(response.meta, customers.value.length);
    } catch {
        toast.error('Could not load customers.');
        customers.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadCustomers(page);
}

function openCreate() {
    editing.value = null;
    clearErrors();
    showModal.value = true;
}

function openEdit(customer) {
    editing.value = customer;
    clearErrors();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
    clearErrors();
}

function handleSubmit({ payload, fields }) {
    clearErrors();

    promptSaveConfirm({
        payload,
        entityLabel: 'Customer',
        mode: editing.value ? 'edit' : 'create',
        fields,
    });
}

async function confirmSave() {
    const payload = pendingPayload.value;

    if (!payload) {
        return;
    }

    saving.value = true;

    try {
        if (editing.value) {
            await updateCustomer(editing.value.id, payload);
            toast.success('Customer updated successfully.');
        } else {
            await createCustomer(payload);
            toast.success('Customer added successfully.');
        }

        finishSaveConfirm();
        closeModal();
        await loadCustomers();
    } catch (error) {
        finishSaveConfirm();
        applyApiError(error, 'Please check the highlighted fields.');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(customer) {
    if (!window.confirm(`Delete customer "${customer.name}"?`)) {
        return;
    }

    try {
        await deleteCustomer(customer.id);
        toast.success('Customer deleted successfully.');
        await loadCustomers();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not delete customer.');
    }
}

onMounted(async () => {
    handleResize();
    window.addEventListener('resize', handleResize);
    await loadCustomers();
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
/* Custom filters single row layout */
:deep(.app-index-collapsible-filters__inner) {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: flex-start;
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

.filter-search-col,
:deep(.filter-search-col) {
    flex: 0 0 320px;
    width: 320px;
}

.customer-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.customer-name-link:hover {
    text-decoration: underline;
}

/* ── Desktop View (>= 768px): keep filter toggle hidden & search box open ─ */
@media (min-width: 768px) {
    .customer-header-btn--filter {
        display: none !important;
    }

    .customers-filters-content {
        display: block !important;
    }
}

/* ── Mobile Responsive Layout (< 768px) ───────────────────────────── */
@media (max-width: 767.98px) {
    :deep(.card-header) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 8px !important;
    }

    :deep(.app-index-card-toolbar) {
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 6px !important;
        width: auto !important;
        margin-top: 0 !important;
    }

    .customer-header-btn,
    :deep(.customer-header-btn) {
        flex: 0 0 32px !important;
        width: 32px !important;
        min-width: 32px !important;
        max-width: 32px !important;
        height: 30px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 6px !important;
    }

    .customer-header-btn i,
    :deep(.customer-header-btn i) {
        font-size: 0.8rem !important;
        margin: 0 !important;
    }

    .customer-header-btn--upload,
    :deep(.customer-header-btn--upload),
    .customer-header-btn--upload i,
    :deep(.customer-header-btn--upload i),
    .customer-header-btn--add,
    :deep(.customer-header-btn--add),
    .customer-header-btn--add i,
    :deep(.customer-header-btn--add i) {
        color: var(--brand-primary, #2fb36f) !important;
    }

    .customer-header-btn--filter,
    :deep(.customer-header-btn--filter),
    .customer-header-btn--filter:hover,
    :deep(.customer-header-btn--filter:hover),
    .customer-header-btn--filter:focus,
    :deep(.customer-header-btn--filter:focus),
    .customer-header-btn--filter:active,
    :deep(.customer-header-btn--filter:active) {
        border-radius: 6px !important;
        border: 1px solid #ffffff !important;
        background-color: #ffffff !important;
        background: #ffffff !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
        color: var(--brand-primary, #2fb36f) !important;
        width: 32px !important;
        min-width: 32px !important;
        height: 30px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .customer-header-btn--filter .filter-slider-icon,
    :deep(.customer-header-btn--filter .filter-slider-icon),
    .customer-header-btn--filter svg,
    :deep(.customer-header-btn--filter svg) {
        color: var(--brand-primary, #2fb36f) !important;
        fill: currentColor !important;
        margin: 0 !important;
    }

    /* Filters: Search input + Search icon + Reset icon in single horizontal line */
    .customers-filters-content,
    :deep(.customers-filters-content),
    :deep(.app-index-collapsible-filters) {
        width: 100% !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 6px !important;
        padding: 0.45rem 0.6rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    :deep(.app-index-filters-fields) {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        width: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .filter-search-col,
    .customers-search-col,
    :deep(.filter-search-col),
    :deep(.customers-search-col),
    :deep(.app-index-filters-fields > div) {
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .customers-search-input,
    :deep(.customers-search-input),
    :deep(.app-index-search) {
        display: flex !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        flex: 1 1 auto !important;
        margin: 0 !important;
    }

    :deep(.customers-filters-content .form-control),
    :deep(.customers-search-input input),
    :deep(.filter-search-col input),
    :deep(.app-index-search input) {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
        height: 34px !important;
        font-size: 0.8125rem !important;
        padding: 0.35rem 0.65rem !important;
        border: 1px solid #ced4da !important;
        border-radius: 6px !important;
        background-color: #ffffff !important;
        color: #1f2937 !important;
        box-sizing: border-box !important;
    }

    :deep(.customers-filters-content input::placeholder),
    :deep(.customers-search-input input::placeholder) {
        color: #94a3b8 !important;
        font-size: 0.78rem !important;
        opacity: 1 !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        flex: 0 0 auto !important;
        align-items: center !important;
        gap: 6px !important;
        margin: 0 !important;
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding: 0 !important;
        width: auto !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn),
    :deep(.app-index-collapsible-filters__btn) {
        font-size: 0 !important;
        width: 34px !important;
        min-width: 34px !important;
        max-width: 34px !important;
        height: 34px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 6px !important;
        flex: 0 0 34px !important;
        margin: 0 !important;
    }

    :deep(.app-index-collapsible-filters__btn i) {
        font-size: 0.85rem !important;
        margin: 0 !important;
        display: inline-block !important;
    }

    :deep(.app-index-table) {
        font-size: 0.72rem !important;
        width: max-content !important;
        min-width: 100% !important;
    }

    :deep(.app-index-table th),
    :deep(.app-index-table td) {
        font-size: 0.72rem !important;
        padding: 0.28rem 0.45rem !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
        line-height: 1.25 !important;
    }

    :deep(.app-index-table a),
    :deep(.customer-name-link) {
        font-size: 0.72rem !important;
    }

    :deep(.app-index-table .app-crud-action-btn),
    :deep(.app-index-table .app-index-actions-cell .btn) {
        padding: 0.15rem 0.35rem !important;
        font-size: 0.72rem !important;
        height: 24px !important;
        min-height: 24px !important;
    }

    :deep(.app-index-table .app-crud-action-btn i),
    :deep(.app-index-table .app-index-actions-cell .btn i) {
        font-size: 0.72rem !important;
    }

    :deep(.card-body) {
        padding: 0 !important;
    }

    :deep(.table-responsive) {
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
}
</style>
