<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company from
            <RouterLink :to="{ name: 'companies.index' }" class="alert-link">Company List</RouterLink>
            and click <strong>Enter</strong> before viewing archived invoices.
        </div>

        <AppIndexListCard title="Archived Invoices" :loading="loading" loading-text="Loading archived invoices…">
            <template #toolbar>
                <RouterLink :to="{ name: 'invoices.index' }" class="btn btn-back btn-sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Back to Invoice List
                </RouterLink>
            </template>

            <AppIndexCollapsibleFilters
                panel-id="invoices-archived-filters-panel"
                :loading="loading"
                @search="loadInvoices(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="invoices_archived_search">Search</label>
                    <AppIndexSearchInput
                        v-model="searchQuery"
                        input-id="invoices_archived_search"
                        placeholder="Search invoice, customer, trip…"
                        max-width="100%"
                        :disabled="loading"
                        @keydown.enter="loadInvoices(1)"
                    />
                </div>
                <div>
                    <label class="form-label">Payment Status</label>
                    <AppSelect
                        v-model="paymentStatusFilter"
                        :options="paymentStatusOptions"
                        placeholder="All statuses"
                        search-placeholder="Search status…"
                        :disabled="loading"
                    />
                </div>
            </AppIndexCollapsibleFilters>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Invoice No.</th>
                        <th>Customer</th>
                        <th>Trip</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th>Deleted</th>
                        <th>Reason</th>
                        <th class="app-index-actions-cell text-end" style="width: 90px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="invoices.length === 0" class="app-index-empty-row">
                        <td colspan="9" class="text-center text-muted py-4">
                            No archived invoices found.
                        </td>
                    </tr>
                    <tr v-for="(invoice, index) in invoices" :key="invoice.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <RouterLink
                                v-if="canOpenInvoice"
                                :to="{ name: 'invoices.show', params: { id: invoice.id } }"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ invoice.invoice_number || '—' }}
                            </RouterLink>
                            <strong v-else>{{ invoice.invoice_number || '—' }}</strong>
                            <div v-if="invoice.document_type_label" class="small text-muted">
                                {{ invoice.document_type_label }}
                            </div>
                        </td>
                        <td>{{ invoice.customer?.name ?? '—' }}</td>
                        <td>
                            <RouterLink
                                v-if="invoice.trip?.id"
                                :to="{ name: 'trips.show', params: { id: invoice.trip.id } }"
                                class="text-decoration-none"
                            >
                                {{ invoice.trip.trip_number }}
                            </RouterLink>
                            <span v-else>—</span>
                        </td>
                        <td>{{ formatDate(invoice.invoice_date) }}</td>
                        <td class="text-end">₹{{ formatMoney(invoice.total_amount) }}</td>
                        <td class="small">
                            <div>{{ formatDate(invoice.archived_at) }}</div>
                            <div v-if="invoice.deleted_by?.name" class="text-muted">
                                by {{ invoice.deleted_by.name }}
                            </div>
                        </td>
                        <td class="small">
                            <span :title="invoice.deletion_reason || ''">
                                {{ truncateReason(invoice.deletion_reason) }}
                            </span>
                        </td>
                        <td class="app-index-actions-cell text-end">
                            <AppTableActions>
                                <AppCrudActionButton
                                    v-if="canOpenInvoice"
                                    action="view"
                                    title="View invoice"
                                    :to="{ name: 'invoices.show', params: { id: invoice.id } }"
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
                    item-label="archived invoices"
                    aria-label="Archived invoices pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { listInvoices } from '../../api/invoices';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexListCard from '../../components/ui/AppIndexListCard.vue';
import AppIndexPartloadPagination from '../../components/ui/AppIndexPartloadPagination.vue';
import AppIndexSearchInput from '../../components/ui/AppIndexSearchInput.vue';
import AppTableActions from '../../components/ui/AppTableActions.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useListPagination } from '../../composables/useListPagination';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatDate } from '../../utils/datetime';

const paymentStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'partial', label: 'Partially Paid' },
    { value: 'paid', label: 'Paid' },
    { value: 'cancelled', label: 'Cancelled' },
];

const toast = useAppToast();
const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext } = useRequireCompanyContext();

const invoices = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const paymentStatusFilter = ref('');
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);

const canOpenInvoice = computed(
    () => canInCompany('invoices.view_archived') || canInCompany('invoices.view'),
);

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function truncateReason(reason) {
    const value = String(reason ?? '').trim();

    if (value === '') {
        return '—';
    }

    return value.length > 60 ? `${value.slice(0, 57)}…` : value;
}

async function loadInvoices(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listInvoices({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
            payment_status: paymentStatusFilter.value,
            archived: 'archived',
        });

        invoices.value = response.data ?? [];
        syncFromMeta(response.meta, invoices.value.length);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Failed to load archived invoices.');
        invoices.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    searchQuery.value = '';
    paymentStatusFilter.value = '';
    pagination.currentPage = 1;
    loadInvoices(1);
}

function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) {
        return;
    }

    loadInvoices(page);
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadInvoices();
    } else {
        loading.value = false;
    }
});
</script>
