<template>
    <div class="container mt-4 app-index-page app-index-page--standard customers-index-page">
        <AppIndexListCard title="Customers" :loading="loading" loading-text="Loading customers…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Add Customer"
                    @click="openCreate"
                >
                    Add Customer
                </AppIndexHeaderButton>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="import"
                    aria-label="Bulk Upload Customers"
                    @click="openBulkUpload"
                    >
                    Bulk Upload
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="customers-filters-panel"
                :loading="loading"
                @search="loadCustomers(1)"
                @reset="clearFilters"
            >
                <div class="filter-search-col">
                    <label class="form-label" for="customersIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="search"
                        input-id="customersIndex_search"
                        placeholder="Search name, GST, PAN, contact"
                        max-width="100%"
                        size="md"
                        :disabled="loading"
                        @keydown.enter="loadCustomers(1)"
                    />
                </div>
            </AppIndexCollapsibleFilters>

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

        <CustomerDeleteConfirmModal
            :show="showDeleteConfirm"
            :customer="deletingCustomer"
            :deleting="deleting"
            @cancel="closeDeleteConfirm"
            @confirm="confirmDelete"
        />

        <AppBulkUploadModal
            :show="showBulkUpload"
            title="Bulk Upload Customers"
            template-name="customers_template.xlsx"
            error-label="Customers"
            :download-template="downloadCustomersTemplate"
            :upload-file="bulkUploadCustomers"
            @close="closeBulkUpload"
            @uploaded="handleBulkUploaded"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { bulkUploadCustomers, createCustomer, deleteCustomer, downloadCustomersTemplate, listCustomers, updateCustomer } from '../../api/customers';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import MasterCrudSaveConfirmModal from '../../components/ui/MasterCrudSaveConfirmModal.vue';
import CustomerFormModal from '../../components/customers/CustomerFormModal.vue';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { useMasterCrudSaveConfirm } from '../../composables/useMasterCrudSaveConfirm';
import { usePermissions } from '../../composables/usePermissions';
import CustomerDeleteConfirmModal from './CustomerDeleteConfirmModal.vue';
import AppBulkUploadModal from '../../components/ui/AppBulkUploadModal.vue';


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
const showDeleteConfirm = ref(false);
const deletingCustomer = ref(null);
const deleting = ref(false);
const showBulkUpload = ref(false);
const search = ref('');
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(10);
const showModal = ref(false);
const editing = ref(null);

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

function handleDelete(customer) {
    deletingCustomer.value = customer;
    showDeleteConfirm.value = true;
}

function closeDeleteConfirm() {
    if (deleting.value) {
        return;
    }
    showDeleteConfirm.value = false;
    deletingCustomer.value = null;
}

async function confirmDelete(payload) {
    if (!deletingCustomer.value) {
        return;
    }

    deleting.value = true;

    try {
        const customerId = deletingCustomer.value.id;

        await deleteCustomer(customerId, payload);

        toast.success('Customer deleted successfully.');

        const page =
            customers.value.length === 1 && pagination.currentPage > 1
                ? pagination.currentPage - 1
                : pagination.currentPage;

        showDeleteConfirm.value = false;
        deletingCustomer.value = null;

        await loadCustomers(page);
    } catch (error) {
        const message =
            error.response?.data?.errors?.deleted_reason?.[0] ??
            error.response?.data?.message ??
            'Could not delete customer.';

        toast.error(message);
    } finally {
        deleting.value = false;
    }
}

function openBulkUpload() {
    showBulkUpload.value = true;
}

function closeBulkUpload() {
    showBulkUpload.value = false;
}

async function handleBulkUploaded(result) {
    await loadCustomers();

    toast.success(
        `Bulk upload completed. ${result?.successful ?? 0} customers imported.`,
    );
}
onMounted(async () => {
    await loadCustomers();
});
</script>

<style scoped>
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

.customer-name-link {
    font-weight: 600;
    color: var(--bs-link-color, #0d6efd);
    text-decoration: none;
}

.customer-name-link:hover {
    text-decoration: underline;
}
</style>
