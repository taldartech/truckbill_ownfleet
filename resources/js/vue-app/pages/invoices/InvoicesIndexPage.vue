<template>
    <div class="container mt-4 app-index-page app-index-page--standard">
        <AppIndexListCard title="Invoice Management" :loading="loading" loading-text="Loading invoices…">
            <template #toolbar>
                <AppIndexHeaderButton
                    v-if="canCreate"
                    icon="plus"
                    aria-label="Create Invoice"
                    @click="goToCreate"
                >
                    Create Invoice
                </AppIndexHeaderButton>
                <RouterLink
                    v-if="canViewArchived"
                    :to="{ name: 'invoices.archived' }"
                    class="btn btn-outline-secondary btn-sm"
                >
                    <i class="bi bi-archive" aria-hidden="true"></i>
                    Archived
                </RouterLink>
                <AppIndexHeaderButton
                    icon="filter"
                    :aria-expanded="filtersOpen"
                    aria-controls="invoices-filters-panel"
                    aria-label="Toggle search filters"
                    @click="toggleFilters"
                >
                    Search filter
                </AppIndexHeaderButton>
            </template>

            <AppIndexCollapsibleFilters
                v-show="filtersOpen"
                panel-id="invoices-filters-panel"
                :loading="loading"
                @search="loadInvoices(1)"
                @reset="clearFilters"
            >
                <div>
                    <label class="form-label" for="invoicesIndex_search">Search</label>
                    <AppIndexSearchInput
                        v-model="searchQuery"
                        input-id="invoicesIndex_search"
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
                <div>
                    <label class="form-label">Due Date</label>
                    <AppSelect
                        v-model="dueFilter"
                        :options="dueFilterOptions"
                        placeholder="All due dates"
                        search-placeholder="Search…"
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
                        <th>Due Date</th>
                        <th class="text-end">Total</th>
                        <th>Payment</th>
                        <th class="app-index-actions-cell text-center" style="width: 125px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="invoices.length === 0" class="app-index-empty-row">
                        <td colspan="9" class="text-center text-muted py-4">
                            No invoices found.
                        </td>
                    </tr>
                    <tr v-for="(invoice, index) in invoices" :key="invoice.id">
                        <td class="text-center">{{ rowNumber(index) }}</td>
                        <td>
                            <RouterLink
                                :to="{ name: 'invoices.show', params: { id: invoice.id } }"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ invoiceLabel(invoice) }}
                            </RouterLink>
                            <div v-if="invoice.document_type_label" class="small text-muted">
                                {{ invoice.document_type_label }}
                            </div>
                        </td>
                        <td>{{ invoice.customer?.name ?? '—' }}</td>
                        <td>
                            <RouterLink
                                v-if="primaryTripId(invoice)"
                                :to="{ name: 'trips.show', params: { id: primaryTripId(invoice) } }"
                                class="text-decoration-none"
                            >
                                {{ tripLabel(invoice) }}
                            </RouterLink>
                            <span v-else>{{ tripLabel(invoice) }}</span>
                        </td>
                        <td>{{ formatDate(invoice.invoice_date) }}</td>
                        <td>
                            <span :class="{ 'text-danger fw-semibold': invoice.is_overdue }">
                                {{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}
                            </span>
                        </td>
                        <td class="text-end">₹{{ formatMoney(invoice.total_amount) }}</td>
                        <td>
                            <span class="badge" :class="paymentStatusBadge(invoice.payment_status)">
                                {{ paymentStatusLabel(invoice.payment_status) }}
                            </span>
                        </td>
                        <td class="app-index-actions-cell text-center">
                            <AppTableActions>
                                <AppCrudActionButton
                                    action="view"
                                    title="View Details"
                                    :to="{ name: 'invoices.show', params: { id: invoice.id } }"
                                />
                                <AppCrudActionButton
                                    action="pdf"
                                    title="View PDF"
                                    @click="openPdf(invoice)"
                                />
                                <AppCrudActionButton
                                    v-if="canRecordPayment"
                                    action="payment"
                                    :title="paymentActionTitle(invoice)"
                                    :disabled="!canRecordPaymentFor(invoice)"
                                    @click="openPaymentModal(invoice)"
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
                    item-label="invoices"
                    aria-label="Invoices pagination"
                    @page-change="goToPage"
                />
            </template>
        </AppIndexListCard>

        <InvoiceRecordPaymentModal
            :show="showPaymentModal"
            :invoice="selectedInvoice"
            @close="closePaymentModal"
            @saved="handlePaymentSaved"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { listInvoices, pdfUrl } from '../../api/invoices';
import InvoiceRecordPaymentModal from '../../components/invoices/InvoiceRecordPaymentModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCrudActionButton from '../../components/ui/AppCrudActionButton.vue';
import AppIndexCollapsibleFilters from '../../components/ui/AppIndexCollapsibleFilters.vue';
import AppIndexHeaderButton from '../../components/ui/AppIndexHeaderButton.vue';
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

const dueFilterOptions = [
    { value: 'overdue', label: 'Overdue' },
    { value: 'due_soon', label: 'Due within 7 days' },
];

const toast = useAppToast();
const router = useRouter();
const { canInCompany } = usePermissions();
const { guardCompanyContext } = useRequireCompanyContext();

const invoices = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const paymentStatusFilter = ref('');
const dueFilter = ref('');
const filtersOpen = ref(false);
const showPaymentModal = ref(false);
const selectedInvoice = ref(null);
const { pagination, syncFromMeta, reset: resetPagination } = useListPagination(15);

const canCreate = computed(() => canInCompany('invoices.create'));
const canViewArchived = computed(() => canInCompany('invoices.view_archived'));
const canRecordPayment = computed(() => canInCompany('invoices.edit'));

function goToCreate() {
    router.push({ name: 'invoices.create' });
}

function tripLabel(invoice) {
    return invoice.trips_label
        || invoice.trip?.trip_number
        || (Array.isArray(invoice.trips) && invoice.trips.length > 1
            ? `${invoice.trips.length} trips`
            : invoice.trips?.[0]?.trip_number)
        || '—';
}

function primaryTripId(invoice) {
    if (invoice.trip?.id) {
        return invoice.trip.id;
    }

    if (Array.isArray(invoice.trips) && invoice.trips.length === 1) {
        return invoice.trips[0]?.id ?? null;
    }

    return null;
}

const filtersActive = computed(
    () =>
        searchQuery.value.trim() !== ''
        || paymentStatusFilter.value !== ''
        || dueFilter.value !== '',
);

function canRecordPaymentFor(invoice) {
    if (!invoice || invoice.is_archived || invoice.payment_status === 'cancelled') {
        return false;
    }

    if (invoice.payment_status === 'paid') {
        return false;
    }

    const total = Number(invoice.total_amount ?? 0);
    const paid = Number(invoice.paid_amount ?? 0);

    return (total - paid) > 0.009;
}

function paymentActionTitle(invoice) {
    if (invoice?.payment_status === 'cancelled') {
        return 'Invoice Cancelled';
    }

    if (invoice?.payment_status === 'paid' || !canRecordPaymentFor(invoice)) {
        return 'Fully Paid';
    }

    return 'Record Payment';
}

function openPdf(invoice) {
    if (!invoice?.id) {
        return;
    }

    window.open(pdfUrl(invoice.id), '_blank', 'noopener');
}

function openPaymentModal(invoice) {
    if (!canRecordPaymentFor(invoice)) {
        return;
    }

    selectedInvoice.value = invoice;
    showPaymentModal.value = true;
}

function closePaymentModal() {
    showPaymentModal.value = false;
    selectedInvoice.value = null;
}

async function handlePaymentSaved() {
    closePaymentModal();
    await loadInvoices();
}

function toggleFilters() {
    filtersOpen.value = !filtersOpen.value;
}

function rowNumber(index) {
    return pagination.from > 0 ? pagination.from + index : index + 1;
}

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function invoiceLabel(invoice) {
    return invoice.invoice_number || '—';
}

function paymentStatusLabel(status) {
    return paymentStatusOptions.find((option) => option.value === status)?.label ?? status ?? '—';
}

function paymentStatusBadge(status) {
    if (status === 'paid') {
        return 'bg-success';
    }

    if (status === 'partial') {
        return 'bg-info text-dark';
    }

    if (status === 'cancelled') {
        return 'bg-secondary';
    }

    return 'bg-warning text-dark';
}

async function loadInvoices(page = pagination.currentPage) {
    loading.value = true;

    try {
        const response = await listInvoices({
            page,
            perPage: pagination.perPage,
            search: searchQuery.value.trim(),
            payment_status: paymentStatusFilter.value,
            due_filter: dueFilter.value,
        });

        invoices.value = response.data ?? [];
        syncFromMeta(response.meta, invoices.value.length);
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Failed to load invoices.');
        invoices.value = [];
        resetPagination();
    } finally {
        loading.value = false;
    }
}

function clearFilters() {
    searchQuery.value = '';
    paymentStatusFilter.value = '';
    dueFilter.value = '';
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

        if (filtersActive.value) {
            filtersOpen.value = true;
        }
    } else {
        loading.value = false;
    }
});
</script>
