<template>
    <AppModal
        :show="show"
        size="xl"
        :loading="saving"
        :dismissible="true"
        :show-header="false"
        :content-class="['app-confirm-modal__content', 'border-0', 'shadow-lg']"
        :body-class="'p-4 pt-5'"
        :footer-class="'app-modal__footer--center border-0 gap-2 flex-wrap'"
        @close="$emit('cancel')"
    >
        <button
            type="button"
            class="btn-close app-confirm-modal__close"
            aria-label="Close"
            :disabled="saving"
            @click="$emit('cancel')"
        ></button>

        <div class="text-center mb-3">
            <div class="app-confirm-modal__icon">
                <i class="bi bi-question-circle"></i>
            </div>
            <h5 class="app-confirm-modal__title mb-1">
                <i class="bi bi-clipboard-check me-1"></i>{{ summary.title }}
            </h5>
            <p class="text-muted small mb-0">{{ summary.message }}</p>
        </div>

        <div class="trip-save-confirm-banner mb-3">
            <i class="bi bi-info-circle"></i>
            <span>Review Trip Details</span>
        </div>

        <div v-if="summary.changeSummary" class="trip-save-confirm-changes mb-3">
            <i class="bi bi-pencil-square"></i>
            <span>
                Updating:
                <strong>{{ summary.changeSummary }}</strong>
            </span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="trip-save-confirm-card trip-save-confirm-card--primary h-100">
                    <div class="trip-save-confirm-card__header">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Basic Info</span>
                    </div>
                    <div class="trip-save-confirm-card__body">
                        <div
                            v-for="field in summary.basicInfoFields"
                            :key="field.label"
                            class="trip-save-confirm-row"
                            :class="{ 'trip-save-confirm-row--changed': field.changed }"
                        >
                            <span class="trip-save-confirm-row__label">{{ field.label }}</span>
                            <div class="trip-save-confirm-row__value-wrap">
                                <span
                                    class="trip-save-confirm-row__value"
                                    :class="{
                                        'trip-save-confirm-row__value--badge': field.label === 'Trip Number',
                                    }"
                                >
                                    {{ field.value }}
                                </span>
                                <span v-if="field.changed" class="badge bg-warning text-dark ms-1">
                                    Updated
                                </span>
                            </div>
                            <div v-if="field.changed && field.previousValue" class="trip-save-confirm-row__prev">
                                <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong>{{ field.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="row g-3 h-100">
                    <div class="col-12">
                        <div class="trip-save-confirm-card trip-save-confirm-card--info">
                            <div class="trip-save-confirm-card__header">
                                <i class="bi bi-people"></i>
                                <span>Parties & Route</span>
                            </div>
                            <div class="trip-save-confirm-card__body">
                                <div
                                    v-for="field in summary.partyFields"
                                    :key="field.label"
                                    class="trip-save-confirm-row"
                                    :class="{ 'trip-save-confirm-row--changed': field.changed }"
                                >
                                    <span class="trip-save-confirm-row__label">{{ field.label }}</span>
                                    <div class="trip-save-confirm-row__value-wrap">
                                        <span class="trip-save-confirm-row__value">{{ field.value }}</span>
                                        <span v-if="field.changed" class="badge bg-warning text-dark ms-1">
                                            Updated
                                        </span>
                                    </div>
                                    <div v-if="field.changed && field.previousValue" class="trip-save-confirm-row__prev">
                                        <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                        <i class="bi bi-arrow-right mx-1"></i>
                                        <strong>{{ field.value }}</strong>
                                    </div>
                                </div>
                                <div class="trip-save-confirm-route-preview">
                                    <span class="trip-save-confirm-route-preview__label">Route</span>
                                    <div class="trip-save-confirm-route-preview__path">
                                        <template
                                            v-for="(part, index) in routePreviewParts"
                                            :key="`${part}-${index}`"
                                        >
                                            <span>{{ part }}</span>
                                            <i
                                                v-if="index < routePreviewParts.length - 1"
                                                class="bi bi-arrow-right"
                                            ></i>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="trip-save-confirm-card trip-save-confirm-card--warning">
                            <div class="trip-save-confirm-card__header">
                                <i class="bi bi-truck"></i>
                                <span>Transport Info</span>
                            </div>
                            <div class="trip-save-confirm-card__body">
                                <div
                                    v-for="field in summary.transportFields"
                                    :key="field.label"
                                    class="trip-save-confirm-row"
                                    :class="{ 'trip-save-confirm-row--changed': field.changed }"
                                >
                                    <span class="trip-save-confirm-row__label">{{ field.label }}</span>
                                    <div class="trip-save-confirm-row__value-wrap">
                                        <span
                                            class="trip-save-confirm-row__value"
                                            :class="{
                                                'trip-save-confirm-row__value--badge': field.label === 'Truck',
                                            }"
                                        >
                                            {{ field.value }}
                                        </span>
                                        <span v-if="field.changed" class="badge bg-warning text-dark ms-1">
                                            Updated
                                        </span>
                                    </div>
                                    <div v-if="field.changed && field.previousValue" class="trip-save-confirm-row__prev">
                                        <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                        <i class="bi bi-arrow-right mx-1"></i>
                                        <strong>{{ field.value }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="summary.routeLegs.length > 0"
            class="trip-save-confirm-card trip-save-confirm-card--route mb-3"
        >
            <div class="trip-save-confirm-card__header">
                <i class="bi bi-signpost-split"></i>
                <span>Round Trip Route</span>
                <span v-if="summary.removedLegCount > 0" class="trip-save-confirm-card__meta text-danger">
                    {{ summary.removedLegCount }} stop(s) removed
                </span>
            </div>
            <div class="trip-save-confirm-card__body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 trip-save-confirm-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>From → To</th>
                                <th>Customer</th>
                                <th>Goods Type</th>
                                <th class="text-end">Weight (Ton)</th>
                                <th class="text-end">Expected Mileage</th>
                                <th class="text-end">Freight Rate (₹/Ton)</th>
                                <th class="text-end">Freight ₹</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="leg in summary.routeLegs"
                                :key="leg.sequence"
                                :class="{ 'trip-save-confirm-table__row--changed': leg.changed }"
                            >
                                <td>
                                    {{ leg.sequence }}
                                    <span v-if="leg.isNew" class="badge bg-success ms-1">New</span>
                                    <span v-else-if="leg.changed" class="badge bg-warning text-dark ms-1">
                                        Updated
                                    </span>
                                </td>
                                <td>
                                    <div>{{ leg.from }} → {{ leg.to }}</div>
                                    <div
                                        v-if="leg.changed && leg.fieldChanges?.length"
                                        class="trip-save-confirm-leg-changes"
                                    >
                                        <div
                                            v-for="change in leg.fieldChanges"
                                            :key="`${leg.sequence}-${change.label}`"
                                            class="trip-save-confirm-leg-changes__item"
                                        >
                                            <span class="text-muted">{{ change.label }}:</span>
                                            <span>{{ change.previous }}</span>
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <strong>{{ change.current }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ leg.customer }}</td>
                                <td>{{ leg.goods }}</td>
                                <td class="text-end">{{ leg.weight }}</td>
                                <td class="text-end">{{ leg.expectedMileage }}</td>
                                <td class="text-end">₹{{ leg.rate }}</td>
                                <td class="text-end">
                                    <span class="trip-save-confirm-amount">₹{{ leg.freight }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            v-if="summary.expenses.length > 0"
            class="trip-save-confirm-card trip-save-confirm-card--expense mb-3"
        >
            <div class="trip-save-confirm-card__header">
                <i class="bi bi-receipt"></i>
                <span>Trip Expenses</span>
                <span
                    v-if="summary.removedExpenseCount > 0"
                    class="trip-save-confirm-card__meta text-danger"
                >
                    {{ summary.removedExpenseCount }} expense(s) removed
                </span>
            </div>
            <div class="trip-save-confirm-card__body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 trip-save-confirm-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Stop</th>
                                <th class="text-end">Amount ₹</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(expense, index) in summary.expenses"
                                :key="index"
                                :class="{ 'trip-save-confirm-table__row--changed': expense.changed }"
                            >
                                <td>
                                    {{ expense.type }}
                                    <span v-if="expense.isNew" class="badge bg-success ms-1">New</span>
                                    <span
                                        v-else-if="expense.changed"
                                        class="badge bg-warning text-dark ms-1"
                                    >
                                        Updated
                                    </span>
                                    <div
                                        v-if="expense.changed && !expense.isNew"
                                        class="trip-save-confirm-expense-changes"
                                    >
                                        <div
                                            v-if="expense.previousType && expense.previousType !== expense.type"
                                            class="trip-save-confirm-expense-changes__item"
                                        >
                                            <span class="text-muted">Type:</span>
                                            {{ expense.previousType }}
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <strong>{{ expense.type }}</strong>
                                        </div>
                                        <div
                                            v-if="expense.previousDate && expense.previousDate !== expense.date"
                                            class="trip-save-confirm-expense-changes__item"
                                        >
                                            <span class="text-muted">Date:</span>
                                            {{ expense.previousDate }}
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <strong>{{ expense.date }}</strong>
                                        </div>
                                        <div
                                            v-if="expense.previousLeg && expense.previousLeg !== expense.leg"
                                            class="trip-save-confirm-expense-changes__item"
                                        >
                                            <span class="text-muted">Stop:</span>
                                            {{ expense.previousLeg }}
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <strong>{{ expense.leg }}</strong>
                                        </div>
                                        <div v-if="expense.previousAmount" class="trip-save-confirm-expense-changes__item">
                                            <span class="text-muted">Amount:</span>
                                            ₹{{ expense.previousAmount }}
                                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                            <strong>₹{{ expense.amount }}</strong>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ expense.date }}</td>
                                <td>{{ expense.leg }}</td>
                                <td class="text-end">
                                    <span class="trip-save-confirm-amount">₹{{ expense.amount }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="trip-save-confirm-totals">
            <div class="row g-2 align-items-stretch">
                <div class="col-6 col-md-3">
                    <div class="trip-save-confirm-totals__item">
                        <span class="trip-save-confirm-totals__label">Total KM</span>
                        <strong>{{ summary.totals.totalKm }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="trip-save-confirm-totals__item">
                        <span class="trip-save-confirm-totals__label">Freight</span>
                        <strong class="trip-save-confirm-amount">₹{{ summary.totals.freight }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="trip-save-confirm-totals__item">
                        <span class="trip-save-confirm-totals__label">Expenses</span>
                        <strong class="trip-save-confirm-amount">₹{{ summary.totals.expenses }}</strong>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="trip-save-confirm-totals__item trip-save-confirm-totals__item--profit">
                        <span class="trip-save-confirm-totals__label">Estimated Profit</span>
                        <strong
                            :class="summary.totals.profitPositive ? 'text-success' : 'text-danger'"
                        >
                            ₹{{ summary.totals.profit }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="btn btn-outline-secondary px-4"
                :disabled="saving"
                @click="$emit('cancel')"
            >
                <i class="bi bi-x-lg me-1"></i>
                Cancel
            </button>
            <button
                v-if="summary.showExpensesAction"
                type="button"
                class="btn btn-outline-success px-4"
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
                class="btn btn-success px-4 fw-semibold"
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
import { computed } from 'vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, required: true },
    saving: { type: Boolean, default: false },
    confirmMode: { type: String, default: 'index' },
});

defineEmits(['cancel', 'confirm']);

const routePreviewParts = computed(() => {
    const preview = props.summary.routePreview ?? '—';

    if (!preview || preview === '—') {
        return ['—'];
    }

    return preview.split(' → ').filter(Boolean);
});
</script>

<style scoped>
.trip-save-confirm-banner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    background: rgba(45, 140, 255, 0.08);
    color: var(--brand-accent-blue, #2d8cff);
    font-size: 0.9rem;
    font-weight: 700;
}

.trip-save-confirm-changes {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    background: #fff8e6;
    border: 1px solid #ffe08a;
    color: #92400e;
    font-size: 0.85rem;
}

.trip-save-confirm-card {
    background: #fff;
    border: 0;
    border-radius: 0.85rem;
    box-shadow: 0 0.125rem 0.5rem rgba(15, 23, 42, 0.06);
    border-top: 3px solid #cbd5e1;
}

.trip-save-confirm-card--primary {
    border-top-color: var(--brand-accent-blue, #2d8cff);
}

.trip-save-confirm-card--info {
    border-top-color: #0ea5e9;
}

.trip-save-confirm-card--warning {
    border-top-color: #d97706;
}

.trip-save-confirm-card--route {
    border-top-color: var(--brand-primary, #2fb36f);
}

.trip-save-confirm-card--expense {
    border-top-color: #64748b;
}

.trip-save-confirm-card__header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.75rem 0.9rem 0.35rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
}

.trip-save-confirm-card__meta {
    margin-left: auto;
    font-size: 0.75rem;
    font-weight: 600;
}

.trip-save-confirm-card__body {
    padding: 0.35rem 0.9rem 0.85rem;
}

.trip-save-confirm-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.15rem 0.5rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.trip-save-confirm-row:last-child {
    border-bottom: 0;
}

.trip-save-confirm-row--changed {
    background: #fffbeb;
    margin: 0 -0.5rem;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    border-radius: 0.375rem;
}

.trip-save-confirm-row__label {
    color: #64748b;
    font-size: 0.8rem;
}

.trip-save-confirm-row__value-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.25rem;
    max-width: 65%;
    text-align: right;
}

.trip-save-confirm-row__value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
}

.trip-save-confirm-row__value--badge {
    background: rgba(45, 140, 255, 0.1);
    color: var(--brand-accent-blue, #2d8cff);
    border-radius: 0.35rem;
    padding: 0.1rem 0.45rem;
}

.trip-save-confirm-row__prev {
    width: 100%;
    font-size: 0.75rem;
    color: #64748b;
    text-align: right;
}

.trip-save-confirm-row__prev-value {
    text-decoration: line-through;
    color: #94a3b8;
}

.trip-save-confirm-leg-changes,
.trip-save-confirm-expense-changes {
    margin-top: 0.35rem;
    font-size: 0.75rem;
}

.trip-save-confirm-leg-changes__item,
.trip-save-confirm-expense-changes__item {
    margin-top: 0.15rem;
}

.trip-save-confirm-route-preview {
    margin-top: 0.35rem;
    padding-top: 0.55rem;
    border-top: 1px dashed #e2e8f0;
}

.trip-save-confirm-route-preview__label {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.35rem;
}

.trip-save-confirm-route-preview__path {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    padding: 0.55rem 0.65rem;
    border-radius: 0.5rem;
    background: #f8fafc;
    font-size: 0.8rem;
    font-weight: 700;
    color: #1e293b;
}

.trip-save-confirm-route-preview__path i {
    color: #94a3b8;
    font-size: 0.7rem;
}

.trip-save-confirm-table thead th {
    background: #f8fafc;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    border-bottom-width: 1px;
}

.trip-save-confirm-table td {
    font-size: 0.85rem;
    vertical-align: middle;
}

.trip-save-confirm-table__row--changed {
    background: #fffbeb;
}

.trip-save-confirm-amount {
    display: inline-block;
    font-weight: 700;
    color: var(--brand-accent-blue, #2d8cff);
    background: rgba(45, 140, 255, 0.1);
    border-radius: 0.35rem;
    padding: 0.15rem 0.45rem;
}

.trip-save-confirm-totals {
    border-radius: 0.85rem;
    padding: 0.85rem;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
}

.trip-save-confirm-totals__item {
    height: 100%;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    padding: 0.75rem;
    text-align: center;
}

.trip-save-confirm-totals__item--profit {
    background: rgba(47, 179, 111, 0.08);
    border-color: rgba(47, 179, 111, 0.25);
}

.trip-save-confirm-totals__label {
    display: block;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.trip-save-confirm-totals__item strong {
    font-size: 1rem;
}
</style>
