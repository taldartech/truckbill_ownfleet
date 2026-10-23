<template>
    <div class="container mt-2 mt-md-4 app-index-page app-index-page--standard">
        <AppIndexListCard
            title="Invoice Management"
            header-class="trips-card-header--gradient"
            :loading="loading"
            loading-text="Loading invoices…"
        >
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
                    class="btn btn-sm btn-light border-0 bg-white shadow-sm text-dark d-inline-flex align-items-center fw-semibold py-1 px-2 rounded-2"
                    title="Archived Invoices"
                    aria-label="Archived Invoices"
                >
                    <svg class="me-1 flex-shrink-0" width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path d="M0 2a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 1 12.5V5a1 1 0 0 1-1-1zm2 3v7.5A1.5 1.5 0 0 0 3.5 14h9a1.5 1.5 0 0 0 1.5-1.5V5zm13-3H1v2h14zM5 7.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5"/>
                    </svg>
                    <span class="d-none d-sm-inline">Archived</span>
                </RouterLink>
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

                <div v-show="filtersOpen" class="invoices-filters-content">
                    <AppIndexCollapsibleFilters
                        panel-id="invoices-filters-panel"
                        :loading="loading"
                        @search="loadInvoices(1)"
                        @reset="clearFilters"
                    >
                        <div class="filter-search-col">
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
                        <div class="filter-status-col">
                            <label class="form-label">Payment Status</label>
                            <AppSelect
                                v-model="paymentStatusFilter"
                                :options="paymentStatusOptions"
                                placeholder="All statuses"
                                search-placeholder="Search status…"
                                :disabled="loading"
                            />
                        </div>
                        <div class="filter-due-col">
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
                </div>
            </template>

            <table class="table table-striped table-hover align-middle mb-0 app-index-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px" class="text-center">#</th>
                        <th class="text-nowrap" style="min-width: 8.5rem;">Invoice No.</th>
                        <th style="min-width: 7.5rem;">Customer</th>
                        <th class="text-nowrap" style="min-width: 7.5rem;">Trip</th>
                        <th class="text-nowrap" style="min-width: 5.5rem;">Date</th>
                        <th class="text-nowrap" style="min-width: 5.5rem;">Due Date</th>
                        <th class="text-end text-nowrap" style="min-width: 5.5rem;">Total</th>
                        <th class="text-nowrap" style="min-width: 6.5rem;">Payment</th>
                        <th class="app-index-actions-cell text-center text-nowrap" style="width: 105px; min-width: 105px;">Actions</th>
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
                        <td class="text-nowrap">
                            <RouterLink
                                :to="{ name: 'invoices.show', params: { id: invoice.id } }"
                                class="text-decoration-none fw-semibold link-primary"
                            >
                                {{ invoiceLabel(invoice) }}
                            </RouterLink>
                            <div v-if="invoice.document_type_label" class="small text-muted" style="font-size: 0.6875rem;">
                                {{ invoice.document_type_label }}
                            </div>
                        </td>
                        <td>
                            <span class="customer-name-cell">{{ invoice.customer?.name ?? '—' }}</span>
                        </td>
                        <td class="text-nowrap">
                            <RouterLink
                                v-if="primaryTripId(invoice)"
                                :to="{ name: 'trips.show', params: { id: primaryTripId(invoice) } }"
                                class="text-decoration-none fw-semibold"
                            >
                                {{ tripLabel(invoice) }}
                            </RouterLink>
                            <span v-else class="text-muted">{{ tripLabel(invoice) }}</span>
                        </td>
                        <td class="text-nowrap">{{ formatDate(invoice.invoice_date) }}</td>
                        <td class="text-nowrap">
                            <span :class="{ 'text-danger fw-semibold': invoice.is_overdue }">
                                {{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap fw-semibold">₹{{ formatMoney(invoice.total_amount) }}</td>
                        <td class="text-nowrap">
                            <span class="badge" :class="paymentStatusBadge(invoice.payment_status)">
                                {{ paymentStatusLabel(invoice.payment_status) }}
                            </span>
                        </td>
                        <td class="app-index-actions-cell text-center text-nowrap">
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

<style scoped>
/* ── Base / Desktop View ───────────────────────────────────────────── */
@media (min-width: 768px) {
    .invoices-filters-content {
        display: block !important;
    }
}

.invoice-header-archived-btn {
    background-color: #ffffff !important;
    background: #ffffff !important;
    color: #000000 !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12) !important;
}

.invoice-header-archived-btn:hover {
    background-color: #f8fafc !important;
    color: #000000 !important;
}

.invoice-archive-icon {
    width: 16px !important;
    height: 16px !important;
    color: #000000 !important;
    fill: currentColor !important;
    display: inline-block !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
}

.filter-search-col {
    min-width: 220px;
    flex: 1 1 220px;
}

.filter-status-col {
    min-width: 160px;
    flex: 0 0 160px;
}

.filter-due-col {
    min-width: 160px;
    flex: 0 0 160px;
}

.filter-search-col :deep(.app-index-search),
.filter-status-col :deep(.app-select),
.filter-due-col :deep(.app-select) {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.app-index-list-card :deep(.app-table-actions) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.app-index-list-card :deep(.app-table-actions .btn-icon) {
    width: 32px !important;
    height: 32px !important;
    min-width: 32px;
    padding: 0 !important;
    flex-shrink: 0;
}

.app-index-list-card :deep(.app-table-actions .btn-icon i) {
    font-size: 0.8125rem;
    line-height: 1;
}

.app-index-list-card :deep(.table) {
    font-size: 0.8125rem;
}

.app-index-list-card :deep(.table th),
.app-index-list-card :deep(.table td) {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}

/* ── Tablet / Middle Screens (768px - 1199.98px) ───────────────────── */
@media (min-width: 768px) and (max-width: 1199.98px) {
    .app-index-list-card :deep(.card-title) {
        font-size: 1.1rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        justify-content: flex-end !important;
        margin-top: 0.25rem !important;
        margin-left: 0 !important;
    }

    .app-index-list-card :deep(.table) {
        font-size: 0.78rem !important;
    }

    .app-index-list-card :deep(.table th),
    .app-index-list-card :deep(.table td) {
        padding: 0.45rem 0.5rem !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon) {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon i) {
        font-size: 0.75rem !important;
    }
}

/* ── Mobile Devices (< 768px) ─────────────────────────────────────── */
@media (max-width: 767.98px) {
    :deep(.app-index-collapsible-filters) {
        padding: 0.65rem 0.75rem !important;
    }

    :deep(.app-index-collapsible-filters__inner) {
        gap: 8px !important;
    }

    :deep(.app-index-filters-fields > div),
    .filter-search-col,
    .filter-status-col,
    .filter-due-col {
        flex: 0 0 calc(50% - 4px) !important;
        width: calc(50% - 4px) !important;
        max-width: calc(50% - 4px) !important;
        min-width: 0 !important;
    }

    /* Filter inputs compact & responsive text sizing on mobile */
    :deep(.app-index-collapsible-filters .form-label) {
        font-size: 0.73rem !important;
        font-weight: 600 !important;
        margin-bottom: 0.2rem !important;
    }

    :deep(.app-index-collapsible-filters .form-control),
    :deep(.app-index-collapsible-filters .select-trigger) {
        height: 33px !important;
        min-height: 33px !important;
        font-size: 0.75rem !important;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        line-height: 1.4 !important;
    }

    :deep(.app-index-collapsible-filters .form-control::placeholder),
    :deep(.app-index-collapsible-filters .select-trigger-label) {
        font-size: 0.75rem !important;
    }

    :deep(.select-trigger-icon) {
        font-size: 0.65rem !important;
    }

    :deep(.app-index-collapsible-filters__actions) {
        width: 100% !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 8px !important;
        margin-top: 0.35rem !important;
        margin-left: 0 !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn) {
        flex: 1 1 0 !important;
        width: calc(50% - 4px) !important;
        min-width: 0 !important;
        max-width: calc(50% - 4px) !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.75rem !important;
        height: 28px !important;
        min-height: 28px !important;
        padding: 0.15rem 0.5rem !important;
        line-height: 1 !important;
        border-radius: 6px !important;
    }

    :deep(.app-index-collapsible-filters__actions .btn i) {
        font-size: 0.7rem !important;
        margin-right: 0.25rem !important;
    }

    /* Table micro adjustments on mobile */
    .app-index-list-card :deep(.table) {
        font-size: 0.72rem !important;
    }

    .app-index-list-card :deep(.table th) {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
    }

    .app-index-list-card :deep(.table td) {
        padding: 0.4rem 0.35rem !important;
        font-size: 0.72rem !important;
    }

    .app-index-list-card :deep(.app-table-actions) {
        gap: 4px !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon) {
        width: 26px !important;
        height: 26px !important;
        min-width: 26px !important;
        border-radius: 0.25rem !important;
    }

    .app-index-list-card :deep(.app-table-actions .btn-icon i) {
        font-size: 0.6875rem !important;
    }

    /* Pagination responsive on mobile */
    :deep(.app-index-partload-pagination) {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        gap: 0.4rem !important;
        padding: 0.5rem 0.6rem !important;
    }

    :deep(.app-index-partload-pagination__summary) {
        font-size: 0.72rem !important;
    }

    :deep(.app-index-partload-pagination__nav) {
        margin-left: 0 !important;
    }

    :deep(.app-index-partload-pagination .page-link) {
        padding: 0.2rem 0.45rem !important;
        font-size: 0.72rem !important;
    }
}

/* ── Extra-Small Mobile Devices (< 380px) ─────────────────────────── */
@media (max-width: 380px) {
    .app-index-list-card :deep(.card-title),
    :deep(.trips-card-header--gradient .card-title) {
        font-size: 0.84rem !important;
    }
}
</style>

