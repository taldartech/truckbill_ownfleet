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
                                                {{ paymentMethodOptions.find((o) => o.value === payment.payment_method)?.label ?? payment.payment_method }}
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
                <!-- Step 1: Total Amount & Balance Due Context -->
                <div v-if="invoice" class="col-12">
                    <div class="p-3 rounded bg-light border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Invoice Total:</span>
                            <span class="fw-semibold">₹{{ formatMoney(invoice.total_amount) }}</span>
                        </div>
                        <div v-if="Number(invoice.paid_amount) > 0" class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Already Settled:</span>
                            <span class="fw-semibold text-success">
                                ₹{{ formatMoney(invoice.paid_amount) }}
                                <small v-if="priorTdsDeducted > 0" class="text-muted fw-normal ms-1">
                                    (incl. ₹{{ formatMoney(priorTdsDeducted) }} TDS)
                                </small>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                            <span class="small fw-bold text-dark">Current Balance to Settle:</span>
                            <span class="fw-bold text-danger fs-6">₹{{ formatMoney(remainingAmount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Customer Deductions Section (TDS & Transit Claims) -->
                <div class="col-12">
                    <div class="p-3 rounded border" style="background-color: #f8fafc;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-dark">
                                <i class="bi bi-scissors me-1 text-danger"></i>1. Customer Deductions (if any)
                            </span>
                            <span class="badge bg-white text-muted border">TDS, Claims & Discount</span>
                        </div>
                        <div class="row g-2 mb-2">
                            <!-- TDS Column: Either Already Deducted Card OR Active TDS Input -->
                            <div class="col-md-6">
                                <div v-if="hasPriorTds && !showTdsOverride" class="p-2.5 rounded bg-white border h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center gap-1.5 text-success fw-semibold small mb-1">
                                            <i class="bi bi-shield-check text-success fs-6"></i>
                                            <span>TDS 194C: ₹{{ formatMoney(priorTdsDeducted) }} Deducted</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.74rem; line-height: 1.3;">
                                            TDS was already recorded in previous payment. No further TDS deduction is needed for this invoice.
                                        </div>
                                    </div>
                                    <div class="pt-2 mt-2 border-top text-end">
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline-secondary py-0.5 px-2"
                                            style="font-size: 0.74rem;"
                                            @click="enableTdsOverride"
                                        >
                                            <i class="bi bi-pencil me-1"></i>Add Additional TDS
                                        </button>
                                    </div>
                                </div>

                                <div v-else>
                                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                                        <label class="form-label small fw-semibold text-muted mb-0">
                                            TDS u/s 194C (₹)
                                            <span v-if="hasPriorTds" class="badge bg-warning-subtle text-warning-emphasis ms-1" style="font-size: 0.65rem;">
                                                Additional
                                            </span>
                                        </label>
                                        <span v-if="tdsBaseAmount > 0" class="text-muted" style="font-size: 0.72rem;">
                                            Base: ₹{{ formatMoney(tdsBaseAmount) }}
                                        </span>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input
                                            v-model="paymentForm.tds_amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form-control"
                                            :class="{ 'is-invalid': paymentFieldErrors.tds_amount }"
                                            placeholder="0.00"
                                            @input="handleDeductionsChanged"
                                        />
                                        <button
                                            v-if="hasPriorTds && showTdsOverride"
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            title="Cancel additional TDS"
                                            @click="cancelTdsOverride"
                                        >
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                    <div v-if="hasPriorTds" class="text-muted mt-1" style="font-size: 0.72rem;">
                                        Already deducted: <strong>₹{{ formatMoney(priorTdsDeducted) }}</strong>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <template v-if="!hasPriorTds">
                                            <button
                                                type="button"
                                                class="btn btn-xs btn-outline-secondary py-0 px-2"
                                                style="font-size: 0.75rem;"
                                                @click="setTdsRate(0.01)"
                                            >
                                                1% <span v-if="tdsBaseAmount > 0">({{ formatMoney(standardTds1Pct) }})</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-xs btn-outline-secondary py-0 px-2"
                                                style="font-size: 0.75rem;"
                                                @click="setTdsRate(0.02)"
                                            >
                                                2% <span v-if="tdsBaseAmount > 0">({{ formatMoney(standardTds2Pct) }})</span>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button
                                                v-if="remainingTds1Pct > 0"
                                                type="button"
                                                class="btn btn-xs btn-outline-secondary py-0 px-2"
                                                style="font-size: 0.75rem;"
                                                @click="setTdsAmount(remainingTds1Pct)"
                                            >
                                                Rem. 1% (₹{{ formatMoney(remainingTds1Pct) }})
                                            </button>
                                            <button
                                                v-if="remainingTds2Pct > 0"
                                                type="button"
                                                class="btn btn-xs btn-outline-secondary py-0 px-2"
                                                style="font-size: 0.75rem;"
                                                @click="setTdsAmount(remainingTds2Pct)"
                                            >
                                                Rem. 2% (₹{{ formatMoney(remainingTds2Pct) }})
                                            </button>
                                        </template>
                                        <button
                                            v-if="Number(paymentForm.tds_amount) > 0"
                                            type="button"
                                            class="btn btn-xs btn-outline-danger py-0 px-2"
                                            style="font-size: 0.75rem;"
                                            @click="clearTds"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                    <div v-if="paymentFieldErrors.tds_amount" class="invalid-feedback d-block">
                                        {{ paymentFieldErrors.tds_amount[0] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <label class="form-label small fw-semibold text-muted mb-0">Shortage / Claims / Discount (₹)</label>
                                    <span v-if="priorClaimsDeducted > 0" class="text-muted" style="font-size: 0.72rem;">
                                        Prev. Deductions: ₹{{ formatMoney(priorClaimsDeducted) }}
                                    </span>
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₹</span>
                                    <input
                                        v-model="paymentForm.deduction_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="form-control"
                                        :class="{ 'is-invalid': paymentFieldErrors.deduction_amount }"
                                        placeholder="0.00"
                                        @input="handleDeductionsChanged"
                                    />
                                </div>
                                <div v-if="paymentFieldErrors.deduction_amount" class="invalid-feedback d-block">
                                    {{ paymentFieldErrors.deduction_amount[0] }}
                                </div>
                                <div class="mt-1.5">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <label class="form-label small fw-semibold text-muted mb-0" style="font-size: 0.75rem;">
                                            Reason <span v-if="Number(paymentForm.deduction_amount) > 0" class="text-danger">*</span>
                                        </label>
                                        <span v-if="Number(paymentForm.deduction_amount) > 0" class="text-danger" style="font-size: 0.7rem; font-weight: 500;">
                                            Required
                                        </span>
                                    </div>
                                    <input
                                        v-model="paymentForm.deduction_reason"
                                        type="text"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': paymentFieldErrors.deduction_reason }"
                                        placeholder="Reason (e.g. Shortage, Discount, Damage, Penalty)"
                                    />
                                    <div v-if="paymentFieldErrors.deduction_reason" class="invalid-feedback d-block">
                                        {{ paymentFieldErrors.deduction_reason[0] }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Live Net After Deductions Box -->
                        <div class="p-2 rounded bg-white border small mt-2">
                            <div class="d-flex justify-content-between text-muted mb-1">
                                <span>Total Deductions:</span>
                                <span class="fw-semibold text-danger">₹{{ formatMoney(totalDeductions) }}</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted">
                                <span class="fw-semibold text-dark">Amount After Deductions (Net Expected):</span>
                                <span class="fw-bold text-primary fs-6">₹{{ formatMoney(netExpectedAmount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Actual Amount Received -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark mb-1">2. Actual Amount Received (₹) *</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input
                            v-model="paymentForm.amount"
                            type="number"
                            min="0"
                            :max="remainingAmount"
                            step="0.01"
                            class="form-control"
                            :class="{ 'is-invalid': paymentFieldErrors.amount }"
                            placeholder="0.00"
                            required
                            @input="userModifiedAmount = true"
                        />
                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            title="Fill exact net amount after deductions"
                            @click="fillNetExpected"
                        >
                            Fill Net
                        </button>
                    </div>
                    <div v-if="paymentFieldErrors.amount" class="invalid-feedback d-block">
                        {{ paymentFieldErrors.amount[0] }}
                    </div>
                    <div class="form-text">
                        Net expected: ₹{{ formatMoney(netExpectedAmount) }} (Balance: ₹{{ formatMoney(remainingAmount) }})
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark mb-1">Payment Date *</label>
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
                <div class="col-md-6">
                    <label class="form-label">Notes / Remark</label>
                    <input
                        v-model="paymentForm.notes"
                        type="text"
                        class="form-control"
                        placeholder="e.g. UTR number, cheque ref, remarks"
                    />
                </div>

                <!-- Step 4: Live Settlement & Status Preview -->
                <div class="col-12">
                    <div class="p-3 rounded border small" :class="projectedStatusBgClass">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="fw-bold text-dark">
                                <i class="bi bi-calculator me-1"></i>Settlement & Status Preview
                            </span>
                            <div class="d-flex align-items-center gap-1">
                                <span class="text-muted">Updated Status:</span>
                                <span class="badge" :class="paymentStatusBadge(projectedStatus)">
                                    {{ paymentStatusLabel(projectedStatus) }}
                                </span>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <span class="text-muted d-block">Total Cleared:</span>
                                <strong class="text-dark">₹{{ formatMoney(totalSettledThisPayment) }}</strong>
                            </div>
                            <div class="col-4">
                                <span class="text-muted d-block">Balance After:</span>
                                <strong :class="balanceAfterPayment > 0 ? 'text-danger' : 'text-success'">
                                    ₹{{ formatMoney(balanceAfterPayment) }}
                                </strong>
                            </div>
                            <div class="col-4 text-end">
                                <span class="text-muted d-block">Projected Total Paid:</span>
                                <strong class="text-success">₹{{ formatMoney(projectedTotalPaid) }}</strong>
                            </div>
                        </div>
                        <div class="pt-2 border-top text-muted" style="font-size: 0.78rem;">
                            <span v-if="projectedStatus === 'paid'" class="text-success fw-medium">
                                <i class="bi bi-check-circle-fill me-1"></i>Invoice will be completely cleared and marked as Paid.
                            </span>
                            <span v-else-if="projectedStatus === 'partial'" class="text-primary fw-medium">
                                <i class="bi bi-info-circle-fill me-1"></i>Invoice will be marked as Partially Paid. Remaining ₹{{ formatMoney(balanceAfterPayment) }} will remain due.
                            </span>
                            <span v-else class="text-muted">
                                Enter payment details above to settle this invoice.
                            </span>
                        </div>
                    </div>
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
                    <div class="app-confirm-card__section-title">Invoice & Customer</div>
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
                                <span class="text-muted">Invoice Total:</span>
                                <strong class="ms-1">₹{{ formatMoney(invoice.total_amount) }}</strong>
                            </span>
                            <span>
                                <span class="text-muted">Already Paid:</span>
                                <span class="ms-1 text-success">₹{{ formatMoney(invoice.paid_amount) }}</span>
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
                            <div class="d-flex justify-content-between pt-1 border-top fw-bold text-dark mt-1">
                                <span>Total Deductions This Payment:</span>
                                <span>₹{{ formatMoney(totalDeductions) }}</span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-baseline mb-2">
                            <span>
                                <span class="text-muted">Amount Received:</span>
                                <strong class="ms-1 text-success fs-6">₹{{ formatMoney(paymentForm.amount) }}</strong>
                                <span class="text-muted ms-1">
                                    on {{ paymentForm.paid_at ? formatDate(paymentForm.paid_at) : '—' }}
                                    <template v-if="paymentMethodLabel"> · {{ paymentMethodLabel }}</template>
                                </span>
                            </span>
                        </div>
                        <div class="p-2 rounded border bg-light">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Total Invoice Cleared:</span>
                                <strong class="text-dark">₹{{ formatMoney(totalSettledThisPayment) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Balance After Payment:</span>
                                <strong :class="balanceAfterPayment > 0 ? 'text-danger' : 'text-success'">
                                    ₹{{ formatMoney(balanceAfterPayment) }}
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                                <span class="text-muted">Updated Status:</span>
                                <span class="badge" :class="paymentStatusBadge(projectedStatus)">
                                    {{ paymentStatusLabel(projectedStatus) }}
                                </span>
                            </div>
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
const userModifiedAmount = ref(false);
const showTdsOverride = ref(false);

const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'upi', label: 'UPI' },
    { value: 'other', label: 'Other' },
];

const paymentForm = reactive({
    amount: '',
    tds_amount: '',
    deduction_amount: '',
    deduction_reason: '',
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

const totalDeductions = computed(() => {
    const tds = Number(paymentForm.tds_amount || 0);
    const ded = Number(paymentForm.deduction_amount || 0);

    return Math.round((tds + ded) * 100) / 100;
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

const standardTds1Pct = computed(() => Math.round(tdsBaseAmount.value * 0.01 * 100) / 100);
const standardTds2Pct = computed(() => Math.round(tdsBaseAmount.value * 0.02 * 100) / 100);

const remainingTds1Pct = computed(() => {
    return Math.max(0, Math.round((standardTds1Pct.value - priorTdsDeducted.value) * 100) / 100);
});

const remainingTds2Pct = computed(() => {
    return Math.max(0, Math.round((standardTds2Pct.value - priorTdsDeducted.value) * 100) / 100);
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
    const currentPaid = Number(invoice.value?.paid_amount || 0);

    return Math.round((currentPaid + totalSettledThisPayment.value) * 100) / 100;
});

const projectedStatus = computed(() => {
    const total = Number(invoice.value?.total_amount || 0);
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
        const maxTds = Math.max(Number(invoice.value?.freight_amount || 0), Number(invoice.value?.total_amount || 0));
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
    if (!invoice.value?.id) {
        return;
    }

    savingPayment.value = true;
    paymentFormError.value = '';
    paymentFieldErrors.value = {};

    try {
        await recordPayment(invoice.value.id, {
            amount: Number(paymentForm.amount || 0),
            tds_amount: Number(paymentForm.tds_amount || 0),
            deduction_amount: Number(paymentForm.deduction_amount || 0),
            deduction_reason: paymentForm.deduction_reason?.trim() || null,
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
