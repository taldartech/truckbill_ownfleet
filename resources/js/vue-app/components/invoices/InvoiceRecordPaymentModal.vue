<template>
    <div>
        <AppModal
            :show="show && !showPaymentConfirm"
            :loading="savingPayment"
            :form-error="paymentFormError"
            as-form
            size="lg"
            body-class="py-2 px-3"
            @close="closeModal"
            @submit="requestPaymentConfirm"
        >
            <template #title>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <i class="bi bi-cash-coin text-success"></i>
                    <span>Record Payment</span>
                    <span v-if="activeInvoice?.invoice_number" class="badge bg-light text-dark border fw-semibold">
                        {{ activeInvoice.invoice_number }}
                    </span>
                    <span v-if="tripNumber" class="badge bg-primary-subtle text-primary border border-primary-subtle fw-medium">
                        Trip #{{ tripNumber }}
                    </span>
                </div>
            </template>

            <div v-if="loadingInvoice" class="text-center py-3 text-muted">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Loading invoice payment details…
            </div>

            <div v-else class="d-flex flex-column gap-2">
                <!-- Step 1: Total Amount & Balance Due Context (Compact Single Row) -->
                <div v-if="activeInvoice" class="py-1 px-2.5 rounded bg-light border d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size: 0.8rem;">
                    <div>
                        <span class="text-muted">Total: </span>
                        <strong class="text-dark">₹{{ formatMoney(activeInvoice.total_amount) }}</strong>
                    </div>
                    <div v-if="Number(activeInvoice.paid_amount) > 0">
                        <span class="text-muted">Settled: </span>
                        <strong class="text-success">₹{{ formatMoney(activeInvoice.paid_amount) }}</strong>
                        <small v-if="priorTdsDeducted > 0" class="text-muted ms-1">(incl. ₹{{ formatMoney(priorTdsDeducted) }} TDS)</small>
                    </div>
                    <div>
                        <span class="text-muted">Current Balance: </span>
                        <strong class="text-danger fs-6">₹{{ formatMoney(remainingAmount) }}</strong>
                    </div>
                </div>

                <!-- Step 2: Customer Deductions Section (TDS & Transit Claims / Discount) -->
                <div class="p-2 rounded border" style="background-color: #f8fafc;">
                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                        <span class="small fw-bold text-dark" style="font-size: 0.8rem;">
                            <i class="bi bi-scissors me-1 text-danger"></i>1. Customer Deductions (if any)
                        </span>
                        <div class="d-flex align-items-center gap-1.5">
                            <span v-if="totalDeductions > 0" class="badge bg-danger-subtle text-danger border border-danger-subtle py-0.5 px-1.5" style="font-size: 0.72rem;">
                                Deductions: ₹{{ formatMoney(totalDeductions) }}
                            </span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1.5" style="font-size: 0.72rem;">
                                Net Expected: ₹{{ formatMoney(netExpectedAmount) }}
                            </span>
                        </div>
                    </div>

                    <div class="row g-2">
                        <!-- TDS Column -->
                        <div class="col-md-5">
                            <div v-if="hasPriorTds && !showTdsOverride" class="p-1.5 rounded bg-white border h-100 d-flex flex-column justify-content-between" style="min-height: 60px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="text-success fw-semibold" style="font-size: 0.76rem;">
                                        <i class="bi bi-shield-check me-1"></i>TDS: ₹{{ formatMoney(priorTdsDeducted) }} Deducted
                                    </span>
                                    <button
                                        type="button"
                                        class="btn btn-link btn-xs p-0 text-decoration-none"
                                        style="font-size: 0.72rem;"
                                        @click="enableTdsOverride"
                                    >
                                        + Add More TDS
                                    </button>
                                </div>
                                <div class="text-muted" style="font-size: 0.7rem; line-height: 1.2;">
                                    TDS was deducted in earlier payment. No further TDS needed.
                                </div>
                            </div>

                            <div v-else>
                                <div class="d-flex justify-content-between align-items-baseline mb-0.5">
                                    <label class="form-label small fw-semibold text-muted mb-0" style="font-size: 0.75rem;">
                                        TDS u/s 194C (₹)
                                        <span v-if="hasPriorTds" class="badge bg-warning-subtle text-warning-emphasis ms-1" style="font-size: 0.65rem;">Addl</span>
                                    </label>
                                    <span v-if="tdsBaseAmount > 0" class="text-muted" style="font-size: 0.7rem;">
                                        Base: ₹{{ formatMoney(tdsBaseAmount) }}
                                    </span>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text py-0.5 px-1.5" style="font-size: 0.75rem;">₹</span>
                                    <input
                                        v-model="paymentForm.tds_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control form-control-sm py-0.5"
                                        :class="{ 'is-invalid': paymentFieldErrors.tds_amount }"
                                        placeholder="0.00"
                                        @input="handleDeductionsChanged"
                                    />
                                    <button
                                        v-if="hasPriorTds && showTdsOverride"
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm py-0 px-1.5"
                                        title="Cancel additional TDS"
                                        @click="cancelTdsOverride"
                                    >
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                    <template v-if="!hasPriorTds">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary py-0 px-1.5"
                                            style="font-size: 0.7rem; line-height: 1.4;"
                                            @click="setTdsRate(0.01)"
                                        >
                                            1% <span v-if="tdsBaseAmount > 0">({{ formatMoney(standardTds1Pct) }})</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary py-0 px-1.5"
                                            style="font-size: 0.7rem; line-height: 1.4;"
                                            @click="setTdsRate(0.02)"
                                        >
                                            2% <span v-if="tdsBaseAmount > 0">({{ formatMoney(standardTds2Pct) }})</span>
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button
                                            v-if="remainingTds1Pct > 0"
                                            type="button"
                                            class="btn btn-outline-secondary py-0 px-1.5"
                                            style="font-size: 0.7rem; line-height: 1.4;"
                                            @click="setTdsAmount(remainingTds1Pct)"
                                        >
                                            Rem. 1% (₹{{ formatMoney(remainingTds1Pct) }})
                                        </button>
                                        <button
                                            v-if="remainingTds2Pct > 0"
                                            type="button"
                                            class="btn btn-outline-secondary py-0 px-1.5"
                                            style="font-size: 0.7rem; line-height: 1.4;"
                                            @click="setTdsAmount(remainingTds2Pct)"
                                        >
                                            Rem. 2% (₹{{ formatMoney(remainingTds2Pct) }})
                                        </button>
                                    </template>
                                    <button
                                        v-if="Number(paymentForm.tds_amount) > 0"
                                        type="button"
                                        class="btn btn-outline-danger py-0 px-1.5"
                                        style="font-size: 0.7rem; line-height: 1.4;"
                                        @click="clearTds"
                                    >
                                        Clear
                                    </button>
                                </div>
                                <div v-if="paymentFieldErrors.tds_amount" class="invalid-feedback d-block" style="font-size: 0.7rem;">
                                    {{ paymentFieldErrors.tds_amount[0] }}
                                </div>
                            </div>
                        </div>

                        <!-- Shortage / Claims / Discount Column -->
                        <div class="col-md-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-0.5">
                                <label class="form-label small fw-semibold text-muted mb-0" style="font-size: 0.75rem;">Shortage / Discount (₹)</label>
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text py-0.5 px-1.5" style="font-size: 0.75rem;">₹</span>
                                <input
                                    v-model="paymentForm.deduction_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control form-control-sm py-0.5"
                                    :class="{ 'is-invalid': paymentFieldErrors.deduction_amount }"
                                    placeholder="0.00"
                                    @input="handleDeductionsChanged"
                                />
                            </div>
                            <div v-if="priorClaimsDeducted > 0" class="text-muted mt-0.5" style="font-size: 0.68rem;">
                                Prev: ₹{{ formatMoney(priorClaimsDeducted) }}
                            </div>
                            <div v-if="paymentFieldErrors.deduction_amount" class="invalid-feedback d-block" style="font-size: 0.7rem;">
                                {{ paymentFieldErrors.deduction_amount[0] }}
                            </div>
                        </div>

                        <!-- Reason Column -->
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-0.5">
                                <label class="form-label small fw-semibold text-muted mb-0" style="font-size: 0.75rem;">
                                    Deduction Reason <span v-if="Number(paymentForm.deduction_amount) > 0" class="text-danger">*</span>
                                </label>
                                <span v-if="Number(paymentForm.deduction_amount) > 0" class="text-danger" style="font-size: 0.68rem; font-weight: 500;">
                                    Required
                                </span>
                            </div>
                            <input
                                v-model="paymentForm.deduction_reason"
                                type="text"
                                class="form-control form-control-sm py-0.5"
                                :class="{ 'is-invalid': paymentFieldErrors.deduction_reason }"
                                placeholder="Shortage, Discount, etc."
                            />
                            <div v-if="paymentFieldErrors.deduction_reason" class="invalid-feedback d-block" style="font-size: 0.7rem;">
                                {{ paymentFieldErrors.deduction_reason[0] }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Payment Details -->
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark mb-0.5" style="font-size: 0.78rem;">
                            2. Amount Received (₹) *
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text py-0.5 px-2">₹</span>
                            <input
                                v-model="paymentForm.amount"
                                type="number"
                                min="0"
                                :max="remainingAmount"
                                step="0.01"
                                class="form-control form-control-sm py-0.5"
                                :class="{ 'is-invalid': paymentFieldErrors.amount }"
                                placeholder="0.00"
                                required
                                @input="userModifiedAmount = true"
                            />
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm py-0.5 px-2"
                                style="font-size: 0.75rem;"
                                title="Fill exact net amount after deductions"
                                @click="fillNetExpected"
                            >
                                Fill Net
                            </button>
                        </div>
                        <div v-if="paymentFieldErrors.amount" class="invalid-feedback d-block" style="font-size: 0.7rem;">
                            {{ paymentFieldErrors.amount[0] }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark mb-0.5" style="font-size: 0.78rem;">Payment Date *</label>
                        <AppDateInput
                            v-model="paymentForm.paid_at"
                            size="sm"
                            :invalid="Boolean(paymentFieldErrors.paid_at)"
                        />
                        <div v-if="paymentFieldErrors.paid_at" class="invalid-feedback d-block" style="font-size: 0.7rem;">
                            {{ paymentFieldErrors.paid_at[0] }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-dark mb-0.5" style="font-size: 0.78rem;">Payment Method</label>
                        <AppSelect
                            v-model="paymentForm.payment_method"
                            :options="paymentMethodOptions"
                            placeholder="Select method"
                            search-placeholder="Search…"
                        />
                    </div>

                    <div class="col-12">
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label small text-muted text-nowrap mb-0" style="font-size: 0.75rem;">Notes / Ref:</label>
                            <input
                                v-model="paymentForm.notes"
                                type="text"
                                class="form-control form-control-sm py-0.5"
                                placeholder="e.g. UTR number, cheque ref, remarks"
                            />
                        </div>
                    </div>
                </div>

                <!-- Step 4: Live Settlement & Status Preview -->
                <div class="py-1 px-2.5 rounded border" :class="projectedStatusBgClass" style="font-size: 0.78rem;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-1.5">
                            <i class="bi bi-calculator text-muted"></i>
                            <span class="fw-semibold text-dark">Settlement Preview:</span>
                            <span class="badge" :class="paymentStatusBadge(projectedStatus)">
                                {{ paymentStatusLabel(projectedStatus) }}
                            </span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span>Total Cleared: <strong class="text-dark">₹{{ formatMoney(totalSettledThisPayment) }}</strong></span>
                            <span>Balance After: <strong :class="balanceAfterPayment > 0 ? 'text-danger' : 'text-success'">₹{{ formatMoney(balanceAfterPayment) }}</strong></span>
                            <span>Projected Paid: <strong class="text-success">₹{{ formatMoney(projectedTotalPaid) }}</strong></span>
                        </div>
                    </div>
                    <div v-if="projectedStatus === 'paid'" class="text-success fw-medium pt-0.5 mt-0.5 border-top" style="font-size: 0.72rem;">
                        <i class="bi bi-check-circle-fill me-1"></i>Invoice will be completely cleared and marked as Paid.
                    </div>
                    <div v-else-if="projectedStatus === 'partial'" class="text-primary fw-medium pt-0.5 mt-0.5 border-top" style="font-size: 0.72rem;">
                        <i class="bi bi-info-circle-fill me-1"></i>Invoice will be marked as Partially Paid. ₹{{ formatMoney(balanceAfterPayment) }} will remain due.
                    </div>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn btn-secondary btn-sm" @click="closeModal">Cancel</button>
                <AppButton type="submit" label="Save Payment" size="sm" :loading="savingPayment" />
            </template>
        </AppModal>

        <!-- Confirmation Modal -->
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
            <div v-if="activeInvoice" class="text-start">
                <div class="app-confirm-card">
                    <div class="app-confirm-card__section-title">Invoice & Customer</div>
                    <div class="app-confirm-card__body small">
                        <div class="d-flex flex-wrap gap-x-3 gap-y-1 mb-2">
                            <span><strong>{{ activeInvoice.invoice_number || '—' }}</strong></span>
                            <span class="text-muted">{{ activeInvoice.customer?.name || '—' }}</span>
                            <span v-if="activeInvoice.trip?.trip_number" class="text-muted">
                                Trip {{ activeInvoice.trip.trip_number }}
                            </span>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <span>
                                <span class="text-muted">Invoice Total:</span>
                                <strong class="ms-1">₹{{ formatMoney(activeInvoice.total_amount) }}</strong>
                            </span>
                            <span>
                                <span class="text-muted">Already Paid:</span>
                                <span class="ms-1 text-success">₹{{ formatMoney(activeInvoice.paid_amount) }}</span>
                            </span>
                            <span>
                                <span class="text-muted">Balance:</span>
                                <strong class="ms-1 text-danger">₹{{ formatMoney(remainingAmount) }}</strong>
                            </span>
                        </div>
                        <hr class="my-2" />
                        <div v-if="totalDeductions > 0 || priorTdsDeducted > 0" class="mb-2 p-2 rounded bg-light border">
                            <div class="fw-semibold text-dark mb-1">Deductions Applied:</div>
                            <div v-if="Number(paymentForm.tds_amount) > 0" class="d-flex justify-content-between text-muted mb-0.5">
                                <span>• {{ hasPriorTds ? 'Additional TDS u/s 194C' : 'TDS u/s 194C Deducted' }}:</span>
                                <span class="fw-semibold text-primary">₹{{ formatMoney(paymentForm.tds_amount) }}</span>
                            </div>
                            <div v-else-if="priorTdsDeducted > 0" class="d-flex justify-content-between text-muted mb-0.5">
                                <span>• TDS u/s 194C (Already Deducted):</span>
                                <span class="fw-semibold text-success">₹{{ formatMoney(priorTdsDeducted) }}</span>
                            </div>
                            <div v-if="Number(paymentForm.deduction_amount) > 0" class="d-flex justify-content-between text-muted mb-0.5">
                                <span>
                                    • Shortage / Claims / Discount:
                                    <small v-if="paymentForm.deduction_reason" class="text-muted">({{ paymentForm.deduction_reason }})</small>
                                </span>
                                <span class="fw-semibold text-danger">₹{{ formatMoney(paymentForm.deduction_amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted pt-1 border-top mt-1">
                                <span>Total Settlement Deductions:</span>
                                <span class="fw-bold text-danger">₹{{ formatMoney(totalDeductions) }}</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1">
                            <span>Amount Received (Bank/Cash):</span>
                            <span class="fw-bold text-dark">₹{{ formatMoney(paymentForm.amount) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1">
                            <span>Total Cleared in This Payment:</span>
                            <span class="fw-bold text-success fs-6">₹{{ formatMoney(totalSettledThisPayment) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1">
                            <span>Remaining Balance After:</span>
                            <strong :class="balanceAfterPayment > 0 ? 'text-danger' : 'text-success'">
                                ₹{{ formatMoney(balanceAfterPayment) }}
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted mb-1">
                            <span>Payment Date:</span>
                            <span>{{ paymentForm.paid_at || '—' }}</span>
                        </div>
                        <div v-if="selectedMethodLabel" class="d-flex justify-content-between text-muted mb-1">
                            <span>Method:</span>
                            <span>{{ selectedMethodLabel }}</span>
                        </div>
                        <div v-if="paymentForm.notes" class="d-flex justify-content-between text-muted">
                            <span>Notes:</span>
                            <span>{{ paymentForm.notes }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AppConfirmModal>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { recordPayment, showInvoice } from '../../api/invoices';
import AppButton from '../ui/AppButton.vue';
import AppConfirmModal from '../ui/AppConfirmModal.vue';
import AppDateInput from '../ui/AppDateInput.vue';
import AppModal from '../ui/AppModal.vue';
import AppSelect from '../ui/AppSelect.vue';
import { useAppToast } from '../../composables/useAppToast';
import { INVOICE_PAYMENT_METHOD_OPTIONS } from '../../constants/invoicePaymentMethod';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    invoice: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const toast = useAppToast();

const paymentMethodOptions = INVOICE_PAYMENT_METHOD_OPTIONS;

const paymentStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'partial', label: 'Partially Paid' },
    { value: 'paid', label: 'Paid' },
    { value: 'cancelled', label: 'Cancelled' },
];

const detailedInvoice = ref(null);
const loadingInvoice = ref(false);

const activeInvoice = computed(() => detailedInvoice.value || props.invoice);

const tripNumber = computed(() => {
    return activeInvoice.value?.trip?.trip_number
        || activeInvoice.value?.trip_number
        || '';
});

const showPaymentConfirm = ref(false);
const savingPayment = ref(false);
const paymentFormError = ref('');
const paymentFieldErrors = ref({});
const userModifiedAmount = ref(false);
const showTdsOverride = ref(false);

const paymentForm = reactive({
    amount: '',
    tds_amount: '',
    deduction_amount: '',
    deduction_reason: '',
    paid_at: new Date().toISOString().slice(0, 10),
    payment_method: '',
    notes: '',
});

const remainingAmount = computed(() => {
    const total = Number(activeInvoice.value?.total_amount ?? 0);
    const paid = Number(activeInvoice.value?.paid_amount ?? 0);

    return Math.max(0, Math.round((total - paid) * 100) / 100);
});

const tdsBaseAmount = computed(() => {
    return Number(activeInvoice.value?.freight_amount || activeInvoice.value?.total_amount || 0);
});

const priorTdsDeducted = computed(() => {
    if (activeInvoice.value?.total_tds_deducted !== undefined && activeInvoice.value?.total_tds_deducted !== null) {
        return Number(activeInvoice.value.total_tds_deducted || 0);
    }
    const list = activeInvoice.value?.payments ?? [];
    return Math.round(list.reduce((sum, p) => sum + Number(p.tds_amount || 0), 0) * 100) / 100;
});

const priorClaimsDeducted = computed(() => {
    if (activeInvoice.value?.total_claims_deducted !== undefined && activeInvoice.value?.total_claims_deducted !== null) {
        return Number(activeInvoice.value.total_claims_deducted || 0);
    }
    const list = activeInvoice.value?.payments ?? [];
    return Math.round(list.reduce((sum, p) => sum + Number(p.deduction_amount || 0), 0) * 100) / 100;
});

const hasPriorTds = computed(() => priorTdsDeducted.value > 0);

const standardTds1Pct = computed(() => Math.round(tdsBaseAmount.value * 0.01 * 100) / 100);
const standardTds2Pct = computed(() => Math.round(tdsBaseAmount.value * 0.02 * 100) / 100);

const remainingTds1Pct = computed(() => {
    return Math.max(0, Math.round((standardTds1Pct.value - priorTdsDeducted.value) * 100) / 100);
});

const remainingTds2Pct = computed(() => {
    return Math.max(0, Math.round((standardTds2Pct.value - priorTdsDeducted.value) * 100) / 100);
});

const totalDeductions = computed(() => {
    const tds = Number(paymentForm.tds_amount || 0);
    const ded = Number(paymentForm.deduction_amount || 0);

    return Math.round((tds + ded) * 100) / 100;
});

const netExpectedAmount = computed(() => {
    const remaining = remainingAmount.value;
    const deductions = totalDeductions.value;

    return Math.max(0, Math.round((remaining - deductions) * 100) / 100);
});

const totalSettledThisPayment = computed(() => {
    const amount = Number(paymentForm.amount || 0);
    const deductions = totalDeductions.value;

    return Math.round((amount + deductions) * 100) / 100;
});

const balanceAfterPayment = computed(() => {
    const remaining = remainingAmount.value;
    const settled = totalSettledThisPayment.value;

    return Math.max(0, Math.round((remaining - settled) * 100) / 100);
});

const projectedTotalPaid = computed(() => {
    const currentPaid = Number(activeInvoice.value?.paid_amount || 0);

    return Math.round((currentPaid + totalSettledThisPayment.value) * 100) / 100;
});

const projectedStatus = computed(() => {
    const total = Number(activeInvoice.value?.total_amount || 0);
    const paid = projectedTotalPaid.value;

    if (paid <= 0) {
        return 'pending';
    }

    if (paid + 0.009 >= total) {
        return 'paid';
    }

    return 'partial';
});

const projectedStatusBgClass = computed(() => {
    if (projectedStatus.value === 'paid') {
        return 'bg-success-subtle border-success-subtle';
    }

    if (projectedStatus.value === 'partial') {
        return 'bg-info-subtle border-info-subtle';
    }

    return 'bg-light border';
});

const selectedMethodLabel = computed(() => {
    return paymentMethodOptions.find((option) => option.value === paymentForm.payment_method)?.label ?? '';
});

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
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

function handleDeductionsChanged() {
    if (!userModifiedAmount.value) {
        paymentForm.amount = netExpectedAmount.value > 0 ? String(netExpectedAmount.value) : '0';
    }
}

function enableTdsOverride() {
    showTdsOverride.value = true;
    if (remainingTds1Pct.value > 0) {
        paymentForm.tds_amount = String(remainingTds1Pct.value);
    } else if (remainingTds2Pct.value > 0) {
        paymentForm.tds_amount = String(remainingTds2Pct.value);
    }
    handleDeductionsChanged();
}

function cancelTdsOverride() {
    showTdsOverride.value = false;
    paymentForm.tds_amount = '';
    handleDeductionsChanged();
}

function setTdsAmount(amount) {
    if (amount > 0) {
        paymentForm.tds_amount = String(Math.round(amount * 100) / 100);
    } else {
        paymentForm.tds_amount = '';
    }
    handleDeductionsChanged();
}

function setTdsRate(pct) {
    const base = tdsBaseAmount.value;
    if (pct > 0 && base > 0) {
        const tds = Math.round((base * pct) * 100) / 100;
        paymentForm.tds_amount = String(tds);
    } else {
        paymentForm.tds_amount = '';
    }
    handleDeductionsChanged();
}

function clearTds() {
    paymentForm.tds_amount = '';
    handleDeductionsChanged();
}

function fillNetExpected() {
    paymentForm.amount = netExpectedAmount.value > 0 ? String(netExpectedAmount.value) : '0';
    userModifiedAmount.value = false;
}

function resetPaymentForm() {
    userModifiedAmount.value = false;
    showTdsOverride.value = false;
    paymentForm.tds_amount = '';
    paymentForm.deduction_amount = '';
    paymentForm.deduction_reason = '';
    paymentForm.amount = remainingAmount.value > 0 ? String(remainingAmount.value) : '';
    paymentForm.paid_at = new Date().toISOString().slice(0, 10);
    paymentForm.payment_method = '';
    paymentForm.notes = '';
    paymentFormError.value = '';
    paymentFieldErrors.value = {};
    showPaymentConfirm.value = false;
}

function closeModal() {
    resetPaymentForm();
    emit('close');
}

function closePaymentConfirm() {
    showPaymentConfirm.value = false;
}

function requestPaymentConfirm() {
    paymentFormError.value = '';
    paymentFieldErrors.value = {};

    const amount = Number(paymentForm.amount || 0);
    const tds = Number(paymentForm.tds_amount || 0);
    const ded = Number(paymentForm.deduction_amount || 0);
    const totalSettled = Math.round((amount + tds + ded) * 100) / 100;
    const errors = {};

    if (!Number.isFinite(amount) || amount < 0) {
        errors.amount = ['Enter a valid payment amount.'];
    }
    if (!Number.isFinite(tds) || tds < 0) {
        errors.tds_amount = ['TDS amount cannot be negative.'];
    } else if (tds > 0) {
        const maxTds = Math.max(Number(activeInvoice.value?.freight_amount || 0), Number(activeInvoice.value?.total_amount || 0));
        if ((priorTdsDeducted.value + tds) > (maxTds + 0.01)) {
            errors.tds_amount = [`Total TDS (₹${formatMoney(priorTdsDeducted.value + tds)}) cannot exceed invoice freight amount of ₹${formatMoney(maxTds)}.`];
        }
    }
    if (!Number.isFinite(ded) || ded < 0) {
        errors.deduction_amount = ['Deduction amount cannot be negative.'];
    } else if (ded > 0 && !paymentForm.deduction_reason?.trim()) {
        errors.deduction_reason = ['Please provide a reason for the deduction / discount (e.g. Shortage, Discount, Damage).'];
    }

    if (totalSettled <= 0) {
        errors.amount = ['Total payment settlement (Amount + TDS + Deductions) must be greater than zero.'];
    } else if (totalSettled - remainingAmount.value > 0.01) {
        errors.amount = [`Total cleared amount (₹${formatMoney(totalSettled)}) cannot exceed the remaining balance of ₹${formatMoney(remainingAmount.value)}.`];
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
    if (!activeInvoice.value?.id) {
        return;
    }

    savingPayment.value = true;

    try {
        const result = await recordPayment(activeInvoice.value.id, {
            amount: Number(paymentForm.amount || 0),
            tds_amount: Number(paymentForm.tds_amount || 0),
            deduction_amount: Number(paymentForm.deduction_amount || 0),
            deduction_reason: paymentForm.deduction_reason?.trim() || null,
            paid_at: paymentForm.paid_at,
            payment_method: paymentForm.payment_method || null,
            notes: paymentForm.notes?.trim() || null,
        });

        toast.success(`Payment of ₹${formatMoney(totalSettledThisPayment.value)} recorded successfully.`);
        showPaymentConfirm.value = false;
        closeModal();
        emit('saved', result);
    } catch (error) {
        if (error.response?.status === 422) {
            paymentFieldErrors.value = error.response.data?.errors ?? {};
            paymentFormError.value = error.response.data?.message ?? 'Please correct the validation errors.';
            showPaymentConfirm.value = false;
        } else {
            toast.error(error.response?.data?.message ?? 'Failed to record payment.');
        }
    } finally {
        savingPayment.value = false;
    }
}

watch(
    () => props.show,
    async (isShown) => {
        if (!isShown) {
            return;
        }

        detailedInvoice.value = null;
        resetPaymentForm();

        if (props.invoice?.id) {
            loadingInvoice.value = true;
            try {
                const fresh = await showInvoice(props.invoice.id);
                detailedInvoice.value = fresh;
            } catch {
                detailedInvoice.value = props.invoice;
            } finally {
                loadingInvoice.value = false;
                resetPaymentForm();
            }
        }
    },
    { immediate: true },
);
</script>
