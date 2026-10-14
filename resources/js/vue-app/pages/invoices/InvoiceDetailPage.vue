<template>
    <div class="container mt-4 invoice-detail-page pb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <RouterLink
                    v-if="invoice?.is_archived"
                    :to="{ name: 'invoices.archived' }"
                    class="text-decoration-none small"
                >
                    <i class="bi bi-arrow-left"></i> Back to Archived Invoices
                </RouterLink>
                <RouterLink
                    v-else
                    :to="{ name: 'invoices.index' }"
                    class="text-decoration-none small"
                >
                    <i class="bi bi-arrow-left"></i> Back to Invoices
                </RouterLink>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                    <h3 class="mb-0">{{ pageTitle }}</h3>
                    <span v-if="invoice?.is_archived" class="badge bg-secondary">Archived</span>
                    <span v-if="invoice" class="badge" :class="paymentStatusBadge(invoice.payment_status)">
                        {{ paymentStatusLabel(invoice.payment_status) }}
                    </span>
                    <span v-if="invoice?.is_overdue" class="badge bg-danger">Overdue</span>
                </div>
                <p v-if="invoice" class="text-muted small mb-0">
                    {{ invoice.document_type_label || invoice.document_type || 'Invoice' }}
                    <span v-if="invoice.customer?.name"> · {{ invoice.customer.name }}</span>
                    <span v-if="invoice.trip?.trip_number"> · Trip {{ invoice.trip.trip_number }}</span>
                    <span v-if="invoice.is_archived && invoice.deletion_reason" class="d-block mt-1">
                        Reason: {{ invoice.deletion_reason }}
                    </span>
                </p>
            </div>
            <div v-if="invoice && !invoice.is_archived" class="d-flex flex-wrap gap-2">
                <AppButton
                    variant="purple"
                    outline
                    size="sm"
                    icon="bi bi-file-earmark-pdf"
                    label="View PDF"
                    @click="openPdf"
                />
                <AppButton
                    v-if="canEditCharges"
                    variant="primary"
                    outline
                    size="sm"
                    icon="bi bi-pencil"
                    label="Edit Charges"
                    @click="openEditChargesModal"
                />
                <AppButton
                    v-if="canRecordPayment"
                    variant="success"
                    outline
                    size="sm"
                    icon="bi bi-cash-coin"
                    label="Record Payment"
                    @click="openPaymentModal"
                />
                <AppButton
                    v-if="canCancel"
                    variant="danger"
                    outline
                    size="sm"
                    icon="bi bi-x-circle"
                    label="Cancel"
                    @click="openCancelConfirm"
                />
                <AppButton
                    v-if="canDelete"
                    variant="danger"
                    size="sm"
                    icon="bi bi-trash"
                    label="Delete"
                    @click="openDeleteConfirm"
                />
            </div>
        </div>

        <div v-if="needsCompanySelection" class="alert alert-warning">
            Select a company and click <strong>Enter</strong> to view invoice details.
        </div>

        <div v-if="invoice?.is_archived" class="alert alert-secondary">
            This invoice is archived and is available for viewing only.
            <span v-if="invoice.deleted_by?.name"> Deleted by {{ invoice.deleted_by.name }}.</span>
        </div>

        <div v-else-if="invoice?.is_cancelled" class="alert alert-secondary">
            This invoice is cancelled and is available for viewing only.
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">Loading invoice details…</div>

        <template v-else-if="invoice">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><strong>Invoice Items</strong></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Route</th>
                                        <th>Goods</th>
                                        <th class="text-end">Weight</th>
                                        <th class="text-end">Freight</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="items.length === 0">
                                        <td colspan="5" class="text-center text-muted py-4">No line items.</td>
                                    </tr>
                                    <tr v-for="item in items" :key="item.id">
                                        <td>{{ item.sequence_no }}</td>
                                        <td v-if="item.from_location || item.to_location">{{ item.from_location || '—' }} → {{ item.to_location || '—' }}</td>
                                        <td v-else-if="item.is_additional_charge">Additional charge</td>
                                        <td v-else>—</td>
                                        <td>{{ item.goods_name || '—' }}</td>
                                        <td class="text-end">{{ item.weight_ton ? formatWeight(item.weight_ton) : '—' }}</td>
                                        <td class="text-end">{{ item.freight_amount ? `₹${formatMoney(item.freight_amount)}` : '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Customer Deductions Section (when deductions exist on invoice) -->
                    <div v-if="hasCustomerDeductions" class="card mt-3 border-start border-3 border-primary">
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-receipt-cutoff text-primary"></i>
                                <strong>Customer Deductions</strong>
                                <span class="badge bg-white text-muted border">TDS & Claims</span>
                            </div>
                            <span class="small text-muted">
                                Total: <strong class="text-danger">₹{{ formatMoney(totalCustomerDeductions) }}</strong>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <!-- TDS 194C Block -->
                                <div v-if="hasPriorTds" :class="priorClaimsDeducted > 0 ? 'col-md-6' : 'col-12'">
                                    <div class="p-3 rounded bg-light border h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="d-flex align-items-center gap-1.5 text-primary fw-semibold small">
                                                    <i class="bi bi-shield-check"></i>
                                                    <span>TDS 194C: ₹{{ formatMoney(priorTdsDeducted) }} Deducted</span>
                                                </div>
                                                <span class="badge bg-primary-subtle text-primary-emphasis" style="font-size: 0.7rem;">u/s 194C</span>
                                            </div>
                                            <p class="text-muted small mb-0" style="font-size: 0.8rem; line-height: 1.4;">
                                                TDS was recorded in payment settlement. Standard TDS base freight: ₹{{ formatMoney(tdsBaseAmount) }}.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shortage / Claims / Discount Block -->
                                <div v-if="priorClaimsDeducted > 0" :class="hasPriorTds ? 'col-md-6' : 'col-12'">
                                    <div class="p-3 rounded bg-light border h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="d-flex align-items-center gap-1.5 text-danger fw-semibold small">
                                                    <i class="bi bi-tag-fill"></i>
                                                    <span>Shortage / Claims / Discount: ₹{{ formatMoney(priorClaimsDeducted) }}</span>
                                                </div>
                                                <span class="badge bg-danger-subtle text-danger-emphasis" style="font-size: 0.7rem;">Deductions</span>
                                            </div>
                                            <div v-if="deductionRecordsList.length > 0" class="mt-1.5">
                                                <div class="text-muted mb-1" style="font-size: 0.75rem;">Recorded reasons:</div>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <span
                                                        v-for="p in deductionRecordsList"
                                                        :key="p.id"
                                                        class="badge bg-white text-dark border fw-normal"
                                                        style="font-size: 0.75rem;"
                                                    >
                                                        ₹{{ formatMoney(p.deduction_amount) }}
                                                        <span v-if="p.deduction_reason" class="text-muted ms-1">({{ p.deduction_reason }})</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Settlement summary bar -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                                <div>
                                    <span>Total Customer Deductions Cleared: </span>
                                    <strong class="text-danger">₹{{ formatMoney(totalCustomerDeductions) }}</strong>
                                </div>
                                <div class="d-flex flex-wrap gap-3">
                                    <span>Received (Bank/Cash): <strong class="text-dark">₹{{ formatMoney(totalAmountReceived) }}</strong></span>
                                    <span>Total Cleared on Invoice: <strong class="text-success">₹{{ formatMoney(invoice.paid_amount) }}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Payment History</strong>
                            <span class="small text-muted">{{ payments.length }} payment(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Received</th>
                                        <th class="text-end">TDS 194C</th>
                                        <th class="text-end">Claims / Discount</th>
                                        <th class="text-end">Total Settled</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="payments.length === 0">
                                        <td colspan="7" class="text-center text-muted py-4">No payments recorded.</td>
                                    </tr>
                                    <tr v-for="payment in payments" :key="payment.id">
                                        <td>{{ formatDate(payment.paid_at) }}</td>
                                        <td>
                                            <span v-if="payment.payment_method" class="badge bg-light text-dark border">
                                                {{ invoicePaymentMethodLabel(payment.payment_method) }}
                                            </span>
                                            <span v-else>—</span>
                                        </td>
                                        <td class="text-end fw-semibold">₹{{ formatMoney(payment.amount) }}</td>
                                        <td class="text-end fw-semibold" :class="Number(payment.tds_amount) > 0 ? 'text-primary' : 'text-muted'">
                                            {{ Number(payment.tds_amount) > 0 ? `₹${formatMoney(payment.tds_amount)}` : '—' }}
                                        </td>
                                        <td class="text-end fw-semibold" :class="Number(payment.deduction_amount) > 0 ? 'text-danger' : 'text-muted'">
                                            <div v-if="Number(payment.deduction_amount) > 0">
                                                <span>₹{{ formatMoney(payment.deduction_amount) }}</span>
                                                <small v-if="payment.deduction_reason" class="text-muted d-block" style="font-size: 0.75rem;">
                                                    ({{ payment.deduction_reason }})
                                                </small>
                                            </div>
                                            <span v-else>—</span>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            ₹{{ formatMoney(payment.total_settled_amount ?? (Number(payment.amount) + Number(payment.tds_amount || 0) + Number(payment.deduction_amount || 0))) }}
                                        </td>
                                        <td class="small text-muted">{{ payment.notes || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="payments.length > 0" class="table-light border-top">
                                    <tr class="fw-semibold">
                                        <td colspan="2" class="text-muted">Total:</td>
                                        <td class="text-end text-dark">₹{{ formatMoney(totalAmountReceived) }}</td>
                                        <td class="text-end" :class="priorTdsDeducted > 0 ? 'text-primary' : 'text-muted'">
                                            {{ priorTdsDeducted > 0 ? `₹${formatMoney(priorTdsDeducted)}` : '—' }}
                                        </td>
                                        <td class="text-end" :class="priorClaimsDeducted > 0 ? 'text-danger' : 'text-muted'">
                                            {{ priorClaimsDeducted > 0 ? `₹${formatMoney(priorClaimsDeducted)}` : '—' }}
                                        </td>
                                        <td class="text-end text-success fw-bold">₹{{ formatMoney(invoice.paid_amount) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><strong>Amounts</strong></div>
                        <div class="card-body small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Route freight (Bilty)</span>
                                <span>₹{{ formatMoney(routeFreightAmount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Additional charges</span>
                                <span>₹{{ formatMoney(customAmount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Taxable amount</span>
                                <span>₹{{ formatMoney(invoice.freight_amount) }}</span>
                            </div>
                            <div v-if="invoice.displays_gst !== false" class="d-flex justify-content-between mb-2">
                                <span class="text-muted">GST ({{ invoice.gst_rate }}%)</span>
                                <span>₹{{ formatMoney(invoice.gst_amount) }}</span>
                            </div>
                            <div v-if="invoice.cgst_amount > 0" class="d-flex justify-content-between mb-1 ps-2">
                                <span class="text-muted">CGST</span>
                                <span>₹{{ formatMoney(invoice.cgst_amount) }}</span>
                            </div>
                            <div v-if="invoice.sgst_amount > 0" class="d-flex justify-content-between mb-1 ps-2">
                                <span class="text-muted">SGST</span>
                                <span>₹{{ formatMoney(invoice.sgst_amount) }}</span>
                            </div>
                            <div v-if="invoice.igst_amount > 0" class="d-flex justify-content-between mb-1 ps-2">
                                <span class="text-muted">IGST</span>
                                <span>₹{{ formatMoney(invoice.igst_amount) }}</span>
                            </div>
                            <hr class="my-2" />
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total</span>
                                <strong>₹{{ formatMoney(invoice.total_amount) }}</strong>
                            </div>
                            <template v-if="hasCustomerDeductions">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Amount Received</span>
                                    <span class="text-dark fw-semibold">₹{{ formatMoney(totalAmountReceived) }}</span>
                                </div>
                                <div v-if="priorTdsDeducted > 0" class="d-flex justify-content-between mb-1 ps-2">
                                    <span class="text-muted">TDS u/s 194C</span>
                                    <span class="text-primary fw-semibold">- ₹{{ formatMoney(priorTdsDeducted) }}</span>
                                </div>
                                <div v-if="priorClaimsDeducted > 0" class="d-flex justify-content-between mb-1 ps-2">
                                    <span class="text-muted">Claims / Discount</span>
                                    <span class="text-danger fw-semibold">- ₹{{ formatMoney(priorClaimsDeducted) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Cleared</span>
                                    <span class="text-success fw-bold">₹{{ formatMoney(invoice.paid_amount) }}</span>
                                </div>
                            </template>
                            <template v-else>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Paid</span>
                                    <span class="text-success">₹{{ formatMoney(invoice.paid_amount) }}</span>
                                </div>
                            </template>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Balance</span>
                                <strong :class="remainingAmount > 0 ? 'text-danger' : 'text-success'">
                                    ₹{{ formatMoney(remainingAmount) }}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header"><strong>Details</strong></div>
                        <div class="card-body small">
                            <div class="mb-2">
                                <span class="text-muted">Invoice Date</span>
                                <div>{{ formatDate(invoice.invoice_date) }}</div>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted">Due Date</span>
                                <div>{{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}</div>
                            </div>
                            <div class="mb-0">
                                <span class="text-muted">Place of Supply</span>
                                <div>{{ invoice.place_of_supply || '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="auditLogs.length > 0" class="card mt-3">
                        <div class="card-header"><strong>Change History</strong></div>
                        <ul class="list-group list-group-flush small">
                            <li
                                v-for="log in auditLogs"
                                :key="log.id"
                                class="list-group-item"
                            >
                                <div class="d-flex justify-content-between gap-2">
                                    <span>{{ formatAuditEvent(log) }}</span>
                                    <span class="text-muted text-nowrap">{{ formatDate(log.performed_at) }}</span>
                                </div>
                                <div v-if="log.performed_by?.name" class="text-muted">
                                    by {{ log.performed_by.name }}
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!needsCompanySelection" class="alert alert-danger">
            Invoice not found or you do not have access.
        </div>

        <InvoiceRecordPaymentModal
            :show="showPaymentModal"
            :invoice="invoice"
            @close="closePaymentModal"
            @saved="handlePaymentSaved"
        />

        <AppConfirmModal
            :show="showCancelConfirm"
            title="Cancel Invoice"
            :message="cancelConfirmMessage"
            confirm-label="Yes, Cancel Invoice"
            confirm-variant="danger"
            :loading="cancelling"
            @cancel="closeCancelConfirm"
            @confirm="confirmCancel"
        />
        <InvoiceEditChargesModal
            ref="editChargesModalRef"
            :show="showEditChargesModal"
            :invoice="invoice"
            :saving="savingCharges"
            :form-error="editChargesFormError"
            :field-errors="editChargesFieldErrors"
            @close="closeEditChargesModal"
            @submit="requestEditChargesSave"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import {
    pdfUrl,
    recordPayment,
    showInvoice,
    cancelInvoice,
    deleteInvoice,
    updateInvoice,
} from '../../api/invoices';
import InvoiceEditChargesModal from '../../components/invoices/InvoiceEditChargesModal.vue';
import InvoiceRecordPaymentModal from '../../components/invoices/InvoiceRecordPaymentModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';
import { useAppToast } from '../../composables/useAppToast';
import { useDeleteConfirm } from '../../composables/useDeleteConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { invoicePaymentMethodLabel } from '../../constants/invoicePaymentMethod';
import { formatDate } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { promptDelete } = useDeleteConfirm();
const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext } = useRequireCompanyContext();

const invoice = ref(null);
const loading = ref(true);
const cancelling = ref(false);
const deleting = ref(false);
const showPaymentModal = ref(false);
const showCancelConfirm = ref(false);
const showEditChargesModal = ref(false);
const savingCharges = ref(false);
const editChargesFormError = ref('');
const editChargesFieldErrors = ref({});
const editChargesModalRef = ref(null);

const canEditCharges = computed(
    () => Boolean(invoice.value?.can_edit_charges) && canInCompany('invoices.edit'),
);

const canRecordPayment = computed(
    () =>
        !invoice.value?.is_cancelled
        && canInCompany('invoices.edit')
        && remainingAmount.value > 0,
);

const canCancel = computed(
    () =>
        !invoice.value?.is_cancelled
        && canInCompany('invoices.delete')
        && Number(invoice.value?.paid_amount ?? 0) <= 0
        && invoice.value?.payment_status !== 'paid',
);

const canDelete = computed(
    () =>
        Boolean(invoice.value?.can_delete)
        && canInCompany('invoices.delete'),
);

const canConfirmDelete = computed(() => {
    const reason = deletionReason.value.trim();

    return reason.length >= 10 && reason.length <= 500;
});

const items = computed(() => invoice.value?.items ?? []);
const payments = computed(() => invoice.value?.payments ?? []);
const auditLogs = computed(() => invoice.value?.audit_logs ?? []);

const routeFreightAmount = computed(() => Number(invoice.value?.route_freight_amount ?? 0));
const customAmount = computed(() => Number(invoice.value?.custom_amount ?? 0));

const remainingAmount = computed(() => {
    const total = Number(invoice.value?.total_amount ?? 0);
    const paid = Number(invoice.value?.paid_amount ?? 0);

    return Math.max(0, total - paid);
});

const pageTitle = computed(() => {
    if (!invoice.value) {
        return 'Invoice Details';
    }

    return invoice.value.invoice_number || 'Invoice Details';
});

const cancelConfirmMessage = computed(() => {
    const number = pageTitle.value;

    return `Cancel invoice ${number}? Linked trip routes will become unbilled again. The invoice will remain available for viewing only, and its number cannot be reused.`;
});

const deleteConfirmMessage = computed(() => {
    const number = pageTitle.value;

    return `Delete invoice ${number}? This removes it from the invoice list. Linked trip routes will become unbilled again, and the invoice number can be reused.`;
});

const tdsBaseAmount = computed(() => {
    return Number(invoice.value?.freight_amount || invoice.value?.total_amount || 0);
});

const priorTdsDeducted = computed(() => {
    if (invoice.value?.total_tds_deducted !== undefined && invoice.value?.total_tds_deducted !== null) {
        return Number(invoice.value.total_tds_deducted || 0);
    }
    const list = invoice.value?.payments ?? [];
    return Math.round(list.reduce((sum, p) => sum + Number(p.tds_amount || 0), 0) * 100) / 100;
});

const priorClaimsDeducted = computed(() => {
    if (invoice.value?.total_claims_deducted !== undefined && invoice.value?.total_claims_deducted !== null) {
        return Number(invoice.value.total_claims_deducted || 0);
    }
    const list = invoice.value?.payments ?? [];
    return Math.round(list.reduce((sum, p) => sum + Number(p.deduction_amount || 0), 0) * 100) / 100;
});

const hasPriorTds = computed(() => priorTdsDeducted.value > 0);

const totalCustomerDeductions = computed(() => {
    return Math.round((priorTdsDeducted.value + priorClaimsDeducted.value) * 100) / 100;
});

const hasCustomerDeductions = computed(() => totalCustomerDeductions.value > 0);

const totalAmountReceived = computed(() => {
    if (invoice.value?.total_amount_received !== undefined && invoice.value?.total_amount_received !== null) {
        return Number(invoice.value.total_amount_received || 0);
    }
    const list = invoice.value?.payments ?? [];
    return Math.round(list.reduce((sum, p) => sum + Number(p.amount || 0), 0) * 100) / 100;
});

const deductionRecordsList = computed(() => {
    const list = invoice.value?.payments ?? [];
    return list.filter((p) => Number(p.deduction_amount || 0) > 0);
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatWeight(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Number(value).toLocaleString('en-IN', { maximumFractionDigits: 3 })} T`;
}

function paymentStatusLabel(status) {
    if (status === 'paid') {
        return 'Paid';
    }

    if (status === 'partial') {
        return 'Partially Paid';
    }

    if (status === 'cancelled') {
        return 'Cancelled';
    }

    return 'Pending';
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

function openPdf() {
    if (!invoice.value?.id) {
        return;
    }

    window.open(pdfUrl(invoice.value.id), '_blank', 'noopener');
}

function formatAuditEvent(log) {
    if (log.event_type === 'charges_updated') {
        const previousTotal = Number(log.payload?.previous?.total_amount ?? 0);
        const currentTotal = Number(log.payload?.current?.total_amount ?? 0);

        return `Additional charges updated (₹${formatMoney(previousTotal)} → ₹${formatMoney(currentTotal)})`;
    }

    return 'Invoice updated';
}

function openEditChargesModal() {
    editChargesFormError.value = '';
    editChargesFieldErrors.value = {};
    showEditChargesModal.value = true;
}

function closeEditChargesModal() {
    if (savingCharges.value) {
        return;
    }

    showEditChargesModal.value = false;
    editChargesFormError.value = '';
    editChargesFieldErrors.value = {};
}

async function requestEditChargesSave() {
    if (!invoice.value?.id || !editChargesModalRef.value) {
        return;
    }

    savingCharges.value = true;
    editChargesFormError.value = '';
    editChargesFieldErrors.value = {};

    try {
        const payload = editChargesModalRef.value.buildPayload();
        invoice.value = await updateInvoice(invoice.value.id, payload);
        toast.success('Invoice charges updated.');
        showEditChargesModal.value = false;
        editChargesFormError.value = '';
        editChargesFieldErrors.value = {};
    } catch (error) {
        if (error.response?.status === 422) {
            editChargesFieldErrors.value = error.response.data.errors ?? {};
            editChargesFormError.value = error.response.data.message ?? 'Please check the highlighted fields.';

            return;
        }

        if (error.response?.status === 403) {
            editChargesFormError.value = 'This invoice cannot be updated.';

            return;
        }

        editChargesFormError.value = error.response?.data?.message ?? 'Could not update invoice charges.';
    } finally {
        savingCharges.value = false;
    }
}

function openPaymentModal() {
    showPaymentModal.value = true;
}

function closePaymentModal() {
    showPaymentModal.value = false;
}

async function handlePaymentSaved() {
    closePaymentModal();
    await loadInvoice();
}

function openCancelConfirm() {
    showCancelConfirm.value = true;
}

function closeCancelConfirm() {
    showCancelConfirm.value = false;
}

async function confirmCancel() {
    if (!invoice.value?.id) {
        return;
    }

    cancelling.value = true;

    try {
        await cancelInvoice(invoice.value.id);
        toast.success('Invoice cancelled.');
        closeCancelConfirm();
        await loadInvoice();
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Could not cancel invoice.');
    } finally {
        cancelling.value = false;
    }
}

function openDeleteConfirm() {
    if (!invoice.value?.id) {
        return;
    }

    promptDelete({
        title: 'Delete Invoice?',
        itemName: invoice.value.invoice_number,
        details: [
            { label: 'Invoice #', value: invoice.value.invoice_number },
            { label: 'Customer', value: invoice.value.customer?.name },
            { label: 'Invoice Date', value: formatDate(invoice.value.invoice_date) },
            { label: 'Total Amount', value: formatMoney(invoice.value.total_amount) },
        ],
        onConfirm: async (remark) => {
            await deleteInvoice(invoice.value.id, {
                deletion_reason: remark,
                deleted_reason: remark,
                remark,
            });
            toast.success('Invoice deleted.');
            await router.push({ name: 'invoices.index' });
        },
    });
}

async function loadInvoice() {
    const invoiceId = route.params.id;

    if (needsCompanySelection.value || !invoiceId) {
        invoice.value = null;
        loading.value = false;

        return;
    }

    loading.value = true;

    try {
        invoice.value = await showInvoice(invoiceId);
    } catch {
        invoice.value = null;
        toast.error('Could not load invoice details.');
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    const ok = await guardCompanyContext();

    if (ok === true) {
        await loadInvoice();
    } else {
        loading.value = false;
    }
});

watch(() => route.params.id, () => {
    loadInvoice();
});
</script>
