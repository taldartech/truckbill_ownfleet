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
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="payments.length === 0">
                                        <td colspan="3" class="text-center text-muted py-4">No payments recorded.</td>
                                    </tr>
                                    <tr v-for="payment in payments" :key="payment.id">
                                        <td>{{ formatDate(payment.paid_at) }}</td>
                                        <td>{{ payment.payment_method || '—' }}</td>
                                        <td class="text-end">₹{{ formatMoney(payment.amount) }}</td>
                                    </tr>
                                </tbody>
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
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Paid</span>
                                <span class="text-success">₹{{ formatMoney(invoice.paid_amount) }}</span>
                            </div>
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

        <AppModal
            :show="showPaymentModal"
            :loading="savingPayment"
            :form-error="paymentFormError"
            as-form
            @close="closePaymentModal"
            @submit="requestPaymentConfirm"
        >
            <template #title>Record Payment</template>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Amount *</label>
                    <input
                        v-model="paymentForm.amount"
                        type="number"
                        min="0.01"
                        :max="remainingAmount"
                        step="0.01"
                        class="form-control"
                        :class="{ 'is-invalid': paymentFieldErrors.amount }"
                        required
                    />
                    <div v-if="paymentFieldErrors.amount" class="invalid-feedback d-block">
                        {{ paymentFieldErrors.amount[0] }}
                    </div>
                    <div class="form-text">Remaining balance: ₹{{ formatMoney(remainingAmount) }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Date *</label>
                    <AppDateInput
                        v-model="paymentForm.paid_at"
                        :invalid="Boolean(paymentFieldErrors.paid_at)"
                    />
                    <div v-if="paymentFieldErrors.paid_at" class="invalid-feedback d-block">
                        {{ paymentFieldErrors.paid_at[0] }}
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method</label>
                    <AppSelect
                        v-model="paymentForm.payment_method"
                        :options="paymentMethodOptions"
                        placeholder="Select method"
                        search-placeholder="Search…"
                    />
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea v-model="paymentForm.notes" class="form-control" rows="2" />
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn btn-secondary" @click="closePaymentModal">Cancel</button>
                <AppButton type="submit" label="Save Payment" :loading="savingPayment" />
            </template>
        </AppModal>

        <AppConfirmModal
            :show="showPaymentConfirm"
            title="Confirm Payment"
            message="Payments cannot be deleted after they are recorded."
            confirm-label="Yes, Record Payment"
            confirm-variant="success"
            confirm-icon="bi bi-cash-coin"
            icon-tone="success"
            icon="bi bi-cash-coin"
            :loading="savingPayment"
            content-class="app-confirm-modal__content app-confirm-modal__content--compact border-0 shadow-lg"
            body-class="app-confirm-modal__body--compact"
            @cancel="closePaymentConfirm"
            @confirm="confirmPayment"
        >
            <div v-if="invoice" class="text-start">
                <div class="app-confirm-card">
                    <div class="app-confirm-card__section-title">Invoice</div>
                    <div class="app-confirm-card__body small">
                        <div class="d-flex flex-wrap gap-x-3 gap-y-1 mb-2">
                            <span><strong>{{ invoice.invoice_number || '—' }}</strong></span>
                            <span class="text-muted">{{ invoice.customer?.name || '—' }}</span>
                            <span v-if="invoice.trip?.trip_number" class="text-muted">
                                Trip {{ invoice.trip.trip_number }}
                            </span>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <span>
                                <span class="text-muted">Total</span>
                                <strong class="ms-1">₹{{ formatMoney(invoice.total_amount) }}</strong>
                            </span>
                            <span>
                                <span class="text-muted">Paid</span>
                                <span class="ms-1 text-success">₹{{ formatMoney(invoice.paid_amount) }}</span>
                            </span>
                            <span>
                                <span class="text-muted">Balance</span>
                                <strong class="ms-1">₹{{ formatMoney(remainingAmount) }}</strong>
                            </span>
                        </div>
                        <hr class="my-2" />
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-baseline">
                            <span>
                                <span class="text-muted">Paying</span>
                                <strong class="ms-1 text-success">₹{{ formatMoney(paymentForm.amount) }}</strong>
                                <span class="text-muted ms-1">
                                    on {{ paymentForm.paid_at ? formatDate(paymentForm.paid_at) : '—' }}
                                    <template v-if="paymentMethodLabel"> · {{ paymentMethodLabel }}</template>
                                </span>
                            </span>
                            <span>
                                <span class="text-muted">After</span>
                                <strong class="ms-1">₹{{ formatMoney(balanceAfterPayment) }}</strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </AppConfirmModal>

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

        <AppConfirmModal
            :show="showDeleteConfirm"
            title="Delete Invoice"
            :message="deleteConfirmMessage"
            confirm-label="Yes, Delete Invoice"
            confirm-variant="danger"
            confirm-icon="bi bi-trash"
            icon="bi bi-trash"
            icon-tone="danger"
            :loading="deleting"
            :confirm-disabled="!canConfirmDelete"
            @cancel="closeDeleteConfirm"
            @confirm="confirmDelete"
        >
            <div class="text-start">
                <label class="form-label mb-1">
                    Reason For Delete <span class="text-danger">*</span>
                </label>
                <textarea
                    v-model="deletionReason"
                    class="form-control form-control-sm"
                    rows="2"
                    placeholder="Why is this invoice being deleted?"
                    :class="{ 'is-invalid': Boolean(deletionReasonError) }"
                    :disabled="deleting"
                ></textarea>
                <div v-if="deletionReasonError" class="invalid-feedback d-block">
                    {{ deletionReasonError }}
                </div>
                <div v-else class="form-text">Minimum 10 characters.</div>
            </div>
        </AppConfirmModal>

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
import AppButton from '../../components/ui/AppButton.vue';
import AppConfirmModal from '../../components/ui/AppConfirmModal.vue';
import AppDateInput from '../../components/ui/AppDateInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import { useAppToast } from '../../composables/useAppToast';
import { usePermissions } from '../../composables/usePermissions';
import { useRequireCompanyContext } from '../../composables/useRequireCompanyContext';
import { formatDate } from '../../utils/datetime';

const route = useRoute();
const router = useRouter();
const toast = useAppToast();
const { canInCompany } = usePermissions();
const { needsCompanySelection, guardCompanyContext } = useRequireCompanyContext();

const invoice = ref(null);
const loading = ref(true);
const savingPayment = ref(false);
const cancelling = ref(false);
const deleting = ref(false);
const showPaymentModal = ref(false);
const showPaymentConfirm = ref(false);
const showCancelConfirm = ref(false);
const showDeleteConfirm = ref(false);
const showEditChargesModal = ref(false);
const savingCharges = ref(false);
const editChargesFormError = ref('');
const editChargesFieldErrors = ref({});
const editChargesModalRef = ref(null);
const deletionReason = ref('');
const deletionReasonError = ref('');
const paymentFormError = ref('');
const paymentFieldErrors = ref({});

const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'upi', label: 'UPI' },
    { value: 'other', label: 'Other' },
];

const paymentForm = reactive({
    amount: '',
    paid_at: '',
    payment_method: '',
    notes: '',
});

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

const paymentMethodLabel = computed(() =>
    paymentMethodOptions.find((option) => option.value === paymentForm.payment_method)?.label ?? '',
);

const balanceAfterPayment = computed(() => {
    const remaining = remainingAmount.value;
    const amount = Number(paymentForm.amount);

    if (!Number.isFinite(amount) || amount <= 0) {
        return remaining;
    }

    return Math.max(0, remaining - amount);
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

function resetPaymentForm() {
    paymentForm.amount = remainingAmount.value > 0 ? String(remainingAmount.value) : '';
    paymentForm.paid_at = new Date().toISOString().slice(0, 10);
    paymentForm.payment_method = '';
    paymentForm.notes = '';
    paymentFormError.value = '';
    paymentFieldErrors.value = {};
}

function openPaymentModal() {
    resetPaymentForm();
    showPaymentModal.value = true;
}

function closePaymentModal() {
    if (savingPayment.value) {
        return;
    }

    showPaymentModal.value = false;
    showPaymentConfirm.value = false;
}

function closePaymentConfirm() {
    if (savingPayment.value) {
        return;
    }

    showPaymentConfirm.value = false;
}

function requestPaymentConfirm() {
    paymentFormError.value = '';
    paymentFieldErrors.value = {};

    const amount = Number(paymentForm.amount);
    const errors = {};

    if (!Number.isFinite(amount) || amount <= 0) {
        errors.amount = ['Enter a valid payment amount.'];
    } else if (amount - remainingAmount.value > 0.01) {
        errors.amount = [`Payment amount cannot exceed the remaining balance of ${remainingAmount.value}.`];
    }

    if (!paymentForm.paid_at) {
        errors.paid_at = ['Payment date is required.'];
    }

    if (Object.keys(errors).length > 0) {
        paymentFieldErrors.value = errors;
        paymentFormError.value = 'Please check the highlighted fields.';

        return;
    }

    showPaymentConfirm.value = true;
}

async function confirmPayment() {
    if (!invoice.value?.id) {
        return;
    }

    savingPayment.value = true;
    paymentFormError.value = '';
    paymentFieldErrors.value = {};

    try {
        await recordPayment(invoice.value.id, {
            amount: Number(paymentForm.amount),
            paid_at: paymentForm.paid_at,
            payment_method: paymentForm.payment_method || null,
            notes: paymentForm.notes?.trim() || null,
        });

        toast.success('Payment recorded.');
        showPaymentConfirm.value = false;
        showPaymentModal.value = false;
        await loadInvoice();
    } catch (error) {
        showPaymentConfirm.value = false;

        if (error.response?.status === 422) {
            paymentFieldErrors.value = error.response.data.errors ?? {};
            paymentFormError.value = error.response.data.message ?? 'Please check the highlighted fields.';

            return;
        }

        paymentFormError.value = error.response?.data?.message ?? 'Could not record payment.';
    } finally {
        savingPayment.value = false;
    }
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
    deletionReason.value = '';
    deletionReasonError.value = '';
    showDeleteConfirm.value = true;
}

function closeDeleteConfirm() {
    showDeleteConfirm.value = false;
    deletionReason.value = '';
    deletionReasonError.value = '';
}

async function confirmDelete() {
    if (!invoice.value?.id || !canConfirmDelete.value) {
        return;
    }

    deleting.value = true;
    deletionReasonError.value = '';

    try {
        await deleteInvoice(invoice.value.id, {
            deletion_reason: deletionReason.value.trim(),
        });
        toast.success('Invoice deleted.');
        closeDeleteConfirm();
        await router.push({ name: 'invoices.index' });
    } catch (error) {
        const reasonError = error.response?.data?.errors?.deletion_reason?.[0];

        if (reasonError) {
            deletionReasonError.value = reasonError;

            return;
        }

        const message = error.response?.data?.errors?.invoice?.[0]
            ?? error.response?.data?.message
            ?? 'Could not delete invoice.';
        toast.error(message);
    } finally {
        deleting.value = false;
    }
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
