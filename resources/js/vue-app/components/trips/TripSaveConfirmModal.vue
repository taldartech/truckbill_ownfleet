<template>
    <AppModal
        :show="show"
        size="xl"
        :loading="saving"
        :dismissible="true"
        :show-header="false"
        :content-class="['app-confirm-modal__content', 'border-0', 'shadow-lg', 'tscm']"
        :body-class="'p-3 tscm__body'"
        :footer-class="'app-modal__footer--center border-0 gap-2 flex-wrap py-2'"
        @close="$emit('cancel')"
    >
        <button
            type="button"
            class="btn-close app-confirm-modal__close"
            aria-label="Close"
            :disabled="saving"
            @click="$emit('cancel')"
        ></button>

        <header class="tscm__header">
            <div class="tscm__header-icon" aria-hidden="true">
                <i class="bi bi-clipboard-check"></i>
            </div>
            <div class="tscm__header-text">
                <h5 class="tscm__title">{{ summary.title }}</h5>
                <p class="tscm__subtitle">{{ summary.message }}</p>
            </div>
        </header>

        <div v-if="summary.changeSummary" class="tscm__changes">
            <i class="bi bi-pencil-square"></i>
            <span>Updating: <strong>{{ summary.changeSummary }}</strong></span>
        </div>

        <section class="tscm__section">
            <div class="tscm__section-head">
                <i class="bi bi-info-circle"></i>
                <span>Trip details</span>
            </div>
            <div class="tscm__meta">
                <div
                    v-for="field in summary.detailFields"
                    :key="field.label"
                    class="tscm__meta-item"
                    :class="{
                        'tscm__meta-item--changed': field.changed,
                    }"
                >
                    <span class="tscm__meta-label">{{ field.label }}</span>
                    <div class="tscm__meta-value-wrap">
                        <span
                            class="tscm__meta-value"
                            :class="{ 'tscm__meta-value--accent': field.accent }"
                        >
                            {{ field.value }}
                        </span>
                        <span v-if="field.changed" class="badge bg-warning text-dark">Updated</span>
                    </div>
                    <div v-if="field.changed && field.previousValue" class="tscm__meta-prev">
                        <span class="tscm__meta-prev-value">{{ field.previousValue }}</span>
                        <i class="bi bi-arrow-right"></i>
                        <strong>{{ field.value }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="summary.routeLegs.length > 0" class="tscm__section">
            <div class="tscm__section-head">
                <i class="bi bi-signpost-split"></i>
                <span>Route stops ({{ summary.routeLegs.length }})</span>
                <span v-if="summary.removedLegCount > 0" class="tscm__section-meta text-danger">
                    {{ summary.removedLegCount }} removed
                </span>
            </div>
            <div class="tscm__table-wrap">
                <table class="table table-sm mb-0 tscm__table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>From → To</th>
                            <th>Customer</th>
                            <th>Pickup</th>
                            <th>Goods</th>
                            <th class="text-end">Units</th>
                            <th class="text-end">Weight</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Freight</th>
                            <th class="text-end">Mileage</th>
                            <th class="text-end">Distance</th>
                            <th>Load</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="leg in summary.routeLegs"
                            :key="leg.sequence"
                            :class="{ 'tscm__table-row--changed': leg.changed }"
                        >
                            <td>
                                {{ leg.sequence }}
                                <span v-if="leg.isNew" class="badge bg-success">New</span>
                                <span v-else-if="leg.changed" class="badge bg-warning text-dark">Updated</span>
                            </td>
                            <td>
                                <div class="tscm__leg-path">{{ leg.from }} → {{ leg.to }}</div>
                                <div
                                    v-if="leg.changed && leg.fieldChanges?.length"
                                    class="tscm__diff-list"
                                >
                                    <div
                                        v-for="change in leg.fieldChanges"
                                        :key="`${leg.sequence}-${change.label}`"
                                        class="tscm__diff-item"
                                    >
                                        <span class="text-muted">{{ change.label }}:</span>
                                        <span>{{ change.previous }}</span>
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <strong>{{ change.current }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td>{{ leg.customer }}</td>
                            <td>{{ leg.pickupDate }}</td>
                            <td>{{ leg.goods }}</td>
                            <td class="text-end">{{ leg.units }}</td>
                            <td class="text-end">{{ leg.weight }}</td>
                            <td class="text-end">₹{{ leg.rate }}</td>
                            <td class="text-end">
                                <span class="tscm__amount">₹{{ leg.freight }}</span>
                            </td>
                            <td class="text-end">{{ leg.expectedMileage }}</td>
                            <td class="text-end">{{ leg.distance }}</td>
                            <td>
                                <span class="badge" :class="leg.loadBadge">{{ leg.load }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="summary.expenses.length > 0" class="tscm__section">
            <div class="tscm__section-head">
                <i class="bi bi-receipt"></i>
                <span>Expenses ({{ summary.expenses.length }})</span>
                <span v-if="summary.removedExpenseCount > 0" class="tscm__section-meta text-danger">
                    {{ summary.removedExpenseCount }} removed
                </span>
            </div>
            <div class="tscm__table-wrap">
                <table class="table table-sm mb-0 tscm__table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Stop</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(expense, index) in summary.expenses"
                            :key="index"
                            :class="{ 'tscm__table-row--changed': expense.changed }"
                        >
                            <td>
                                {{ expense.type }}
                                <span v-if="expense.isNew" class="badge bg-success">New</span>
                                <span
                                    v-else-if="expense.changed"
                                    class="badge bg-warning text-dark"
                                >Updated</span>
                                <div
                                    v-if="expense.changed && !expense.isNew"
                                    class="tscm__diff-list"
                                >
                                    <div
                                        v-if="expense.previousType && expense.previousType !== expense.type"
                                        class="tscm__diff-item"
                                    >
                                        <span class="text-muted">Type:</span>
                                        {{ expense.previousType }}
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <strong>{{ expense.type }}</strong>
                                    </div>
                                    <div
                                        v-if="expense.previousDate && expense.previousDate !== expense.date"
                                        class="tscm__diff-item"
                                    >
                                        <span class="text-muted">Date:</span>
                                        {{ expense.previousDate }}
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <strong>{{ expense.date }}</strong>
                                    </div>
                                    <div
                                        v-if="expense.previousLeg && expense.previousLeg !== expense.leg"
                                        class="tscm__diff-item"
                                    >
                                        <span class="text-muted">Stop:</span>
                                        {{ expense.previousLeg }}
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <strong>{{ expense.leg }}</strong>
                                    </div>
                                    <div
                                        v-if="expense.previousNotes"
                                        class="tscm__diff-item"
                                    >
                                        <span class="text-muted">Notes:</span>
                                        {{ expense.previousNotes }}
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <strong>{{ expense.notes }}</strong>
                                    </div>
                                    <div v-if="expense.previousAmount" class="tscm__diff-item">
                                        <span class="text-muted">Amount:</span>
                                        ₹{{ expense.previousAmount }}
                                        <i class="bi bi-arrow-right text-muted"></i>
                                        <strong>₹{{ expense.amount }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td>{{ expense.date }}</td>
                            <td>{{ expense.leg }}</td>
                            <td class="tscm__notes-cell">{{ expense.notes }}</td>
                            <td class="text-end">
                                <span class="tscm__amount">₹{{ expense.amount }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tscm__totals">
            <div class="tscm__total">
                <span class="tscm__total-label">Full</span>
                <strong>{{ summary.totals.fullLoadStops }}</strong>
            </div>
            <div class="tscm__total">
                <span class="tscm__total-label">Half</span>
                <strong>{{ summary.totals.halfLoadStops }}</strong>
            </div>
            <div class="tscm__total">
                <span class="tscm__total-label">Empty</span>
                <strong>{{ summary.totals.emptyStops }}</strong>
            </div>
            <div class="tscm__total">
                <span class="tscm__total-label">Total KM</span>
                <strong>{{ summary.totals.totalKm }}</strong>
            </div>
            <div class="tscm__total">
                <span class="tscm__total-label">Mileage</span>
                <strong>{{ summary.totals.expectedMileage }}</strong>
            </div>
            <div class="tscm__total">
                <span class="tscm__total-label">Freight</span>
                <strong class="tscm__amount">₹{{ summary.totals.freight }}</strong>
            </div>
            <div class="tscm__total">
                <span class="tscm__total-label">Expenses</span>
                <strong class="tscm__amount">₹{{ summary.totals.expenses }}</strong>
            </div>
            <div class="tscm__total tscm__total--profit">
                <span class="tscm__total-label">Est. Profit</span>
                <strong :class="summary.totals.profitPositive ? 'text-success' : 'text-danger'">
                    ₹{{ summary.totals.profit }}
                </strong>
            </div>
        </section>

        <template #footer>
            <button
                type="button"
                class="btn btn-outline-secondary px-3"
                :disabled="saving"
                @click="$emit('cancel')"
            >
                Cancel
            </button>
            <button
                v-if="summary.showExpensesAction"
                type="button"
                class="btn btn-outline-success px-3"
                :disabled="saving"
                @click="$emit('confirm', 'expenses')"
            >
                <span
                    v-if="saving && confirmMode === 'expenses'"
                    class="spinner-border spinner-border-sm me-1"
                    role="status"
                    aria-hidden="true"
                ></span>
                <i v-else class="bi bi-receipt me-1"></i>
                {{ summary.expensesActionLabel ?? 'Save & Add Expenses' }}
            </button>
            <button
                type="button"
                class="btn btn-success px-3 fw-semibold"
                :disabled="saving"
                @click="$emit('confirm', 'index')"
            >
                <span
                    v-if="saving && confirmMode !== 'expenses'"
                    class="spinner-border spinner-border-sm me-1"
                    role="status"
                    aria-hidden="true"
                ></span>
                <i v-else class="bi bi-check-lg me-1"></i>
                {{ summary.confirmLabel ?? 'Yes, Save Trip' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import AppModal from '../ui/AppModal.vue';

defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, required: true },
    saving: { type: Boolean, default: false },
    confirmMode: { type: String, default: 'index' },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.tscm__body {
    max-height: min(78vh, 820px);
    overflow-y: auto;
}

.tscm__header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    padding-right: 1.5rem;
}

.tscm__header-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 0.65rem;
    background: rgba(47, 179, 111, 0.12);
    color: var(--brand-primary, #2fb36f);
    font-size: 1.15rem;
}

.tscm__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.tscm__subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.35;
}

.tscm__changes {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.75rem;
    padding: 0.45rem 0.65rem;
    border-radius: 0.5rem;
    background: #fff8e6;
    border: 1px solid #ffe08a;
    color: #92400e;
    font-size: 0.78rem;
}

.tscm__section {
    margin-bottom: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    background: #fff;
    overflow: hidden;
}

.tscm__section-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.7rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.tscm__section-meta {
    margin-left: auto;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: none;
    letter-spacing: 0;
}

.tscm__meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0;
}

.tscm__meta-item {
    padding: 0.45rem 0.7rem;
    border-right: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
    min-width: 0;
}

.tscm__meta-item:nth-child(4n) {
    border-right: 0;
}

.tscm__meta-item--changed {
    background: #fffbeb;
}

.tscm__meta-label {
    display: block;
    font-size: 0.68rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.15rem;
}

.tscm__meta-value-wrap {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.tscm__meta-value {
    font-size: 0.82rem;
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
}

.tscm__meta-value--accent {
    display: inline-block;
    padding: 0.05rem 0.4rem;
    border-radius: 0.3rem;
    background: rgba(45, 140, 255, 0.1);
    color: var(--brand-accent-blue, #2d8cff);
}

.tscm__meta-prev {
    margin-top: 0.2rem;
    font-size: 0.7rem;
    color: #64748b;
}

.tscm__meta-prev-value {
    text-decoration: line-through;
    color: #94a3b8;
}

.tscm__table-wrap {
    overflow-x: auto;
}

.tscm__table {
    --bs-table-bg: transparent;
}

.tscm__table thead th {
    white-space: nowrap;
    background: #f8fafc;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    border-bottom-width: 1px;
    padding: 0.4rem 0.55rem;
}

.tscm__table td {
    font-size: 0.78rem;
    vertical-align: top;
    padding: 0.45rem 0.55rem;
    white-space: nowrap;
}

.tscm__table td:nth-child(2),
.tscm__notes-cell {
    white-space: normal;
    min-width: 8rem;
    max-width: 14rem;
}

.tscm__table-row--changed {
    background: #fffbeb;
}

.tscm__leg-path {
    font-weight: 600;
    white-space: normal;
}

.tscm__diff-list {
    margin-top: 0.25rem;
}

.tscm__diff-item {
    margin-top: 0.1rem;
    font-size: 0.7rem;
    color: #64748b;
}

.tscm__diff-item i {
    margin: 0 0.2rem;
    font-size: 0.65rem;
}

.tscm__amount {
    display: inline-block;
    font-weight: 700;
    color: var(--brand-accent-blue, #2d8cff);
    background: rgba(45, 140, 255, 0.1);
    border-radius: 0.3rem;
    padding: 0.1rem 0.35rem;
}

.tscm__totals {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: 0.4rem;
}

.tscm__total {
    min-width: 0;
    text-align: center;
    padding: 0.45rem 0.3rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: #fff;
}

.tscm__total--profit {
    background: rgba(47, 179, 111, 0.08);
    border-color: rgba(47, 179, 111, 0.25);
}

.tscm__total-label {
    display: block;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    margin-bottom: 0.15rem;
}

.tscm__total strong {
    font-size: 0.82rem;
    color: #0f172a;
    word-break: break-word;
}

@media (max-width: 1199.98px) {
    .tscm__meta {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .tscm__meta-item:nth-child(4n) {
        border-right: 1px solid #f1f5f9;
    }

    .tscm__meta-item:nth-child(3n) {
        border-right: 0;
    }

    .tscm__totals {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .tscm__meta {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .tscm__meta-item:nth-child(3n) {
        border-right: 1px solid #f1f5f9;
    }

    .tscm__meta-item:nth-child(2n) {
        border-right: 0;
    }

    .tscm__totals {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
