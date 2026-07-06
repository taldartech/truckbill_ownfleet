<template>
    <div
        v-if="show"
        class="modal fade show d-block trip-save-confirm-modal"
        tabindex="-1"
        style="background: rgba(15, 23, 42, 0.55)"
        @click.self="$emit('cancel')"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content trip-save-confirm-modal__content border-0 shadow-lg">
                <button
                    type="button"
                    class="btn-close trip-save-confirm-modal__close"
                    aria-label="Close"
                    @click="$emit('cancel')"
                ></button>

                <div class="modal-body p-4 pt-5">
                    <div class="text-center mb-3">
                        <div class="trip-save-confirm-modal__icon">
                            <i class="bi bi-question-circle"></i>
                        </div>
                        <h5 class="trip-save-confirm-modal__title mb-1">
                            <i class="bi bi-receipt me-1"></i>{{ summary.title }}
                        </h5>
                        <p class="text-muted small mb-0">{{ summary.message }}</p>
                    </div>

                    <div class="trip-save-confirm-banner mb-3">
                        <i class="bi bi-info-circle"></i>
                        <span>
                            {{ summary.tripNumber || 'Trip' }}
                            <span v-if="summary.vehicleLabel"> · {{ summary.vehicleLabel }}</span>
                        </span>
                    </div>

                    <div v-if="summary.changeSummary" class="trip-save-confirm-changes mb-3">
                        <i class="bi bi-pencil-square"></i>
                        <span>
                            Updating:
                            <strong>{{ summary.changeSummary }}</strong>
                        </span>
                    </div>

                    <div class="trip-save-confirm-card trip-save-confirm-card--expense mb-3">
                        <div class="trip-save-confirm-card__header">
                            <i class="bi bi-receipt"></i>
                            <span>Expense Entries</span>
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
                                            v-for="(row, index) in summary.rows"
                                            :key="`expense-${index}`"
                                            :class="{ 'trip-save-confirm-table__row--changed': row.changed }"
                                        >
                                            <td>
                                                {{ row.type }}
                                                <span v-if="row.isNew" class="badge bg-success ms-1">New</span>
                                                <span
                                                    v-else-if="row.changed"
                                                    class="badge bg-warning text-dark ms-1"
                                                >
                                                    Updated
                                                </span>
                                                <div
                                                    v-if="row.fieldChanges?.length"
                                                    class="trip-save-confirm-expense-changes"
                                                >
                                                    <div
                                                        v-for="change in row.fieldChanges"
                                                        :key="`${index}-${change.label}`"
                                                        class="trip-save-confirm-expense-changes__item"
                                                    >
                                                        <span class="text-muted">{{ change.label }}:</span>
                                                        <span>{{ change.previous }}</span>
                                                        <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                                        <strong>{{ change.current }}</strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ row.date }}</td>
                                            <td>{{ row.leg }}</td>
                                            <td class="text-end">
                                                <span class="trip-save-confirm-amount">₹{{ row.amount }}</span>
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="(row, index) in summary.removedRows"
                                            :key="`removed-${index}`"
                                            class="trip-save-confirm-table__row--removed"
                                        >
                                            <td>
                                                {{ row.type }}
                                                <span class="badge bg-danger ms-1">Removed</span>
                                            </td>
                                            <td>{{ row.date }}</td>
                                            <td>{{ row.leg }}</td>
                                            <td class="text-end text-muted text-decoration-line-through">
                                                ₹{{ row.amount }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="trip-save-confirm-totals">
                        <div class="row g-2 align-items-stretch">
                            <div class="col-6">
                                <div class="trip-save-confirm-totals__item">
                                    <span class="trip-save-confirm-totals__label">Previous Total</span>
                                    <strong>₹{{ summary.totals.previous }}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="trip-save-confirm-totals__item trip-save-confirm-totals__item--profit">
                                    <span class="trip-save-confirm-totals__label">New Total</span>
                                    <strong class="trip-save-confirm-amount">₹{{ summary.totals.current }}</strong>
                                    <div v-if="summary.totals.changed" class="small text-muted mt-1">
                                        <span class="text-decoration-line-through">₹{{ summary.totals.previous }}</span>
                                        <i class="bi bi-arrow-right mx-1"></i>
                                        ₹{{ summary.totals.current }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 justify-content-center gap-2 pb-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('cancel')">
                        <i class="bi bi-x-lg me-1"></i>
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-success px-4 fw-semibold"
                        :disabled="saving"
                        @click="$emit('confirm')"
                    >
                        <span
                            v-if="saving"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        ></span>
                        <i v-else class="bi bi-check-lg me-1"></i>
                        {{ summary.confirmLabel ?? 'Yes, Save Expenses' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, required: true },
    saving: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.trip-save-confirm-modal__content {
    border-radius: 1rem;
    overflow: hidden;
}

.trip-save-confirm-modal__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 2;
}

.trip-save-confirm-modal__icon {
    width: 4rem;
    height: 4rem;
    margin: 0 auto 0.75rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(45, 140, 255, 0.12);
    color: var(--brand-accent-blue, #2d8cff);
    font-size: 2rem;
    line-height: 1;
}

.trip-save-confirm-modal__title {
    font-weight: 700;
    color: #1e293b;
}

.trip-save-confirm-banner,
.trip-save-confirm-changes {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    font-size: 0.85rem;
}

.trip-save-confirm-banner {
    background: rgba(45, 140, 255, 0.08);
    color: var(--brand-accent-blue, #2d8cff);
    font-weight: 700;
}

.trip-save-confirm-changes {
    background: #fff8e6;
    border: 1px solid #ffe08a;
    color: #92400e;
}

.trip-save-confirm-card {
    background: #fff;
    border-radius: 0.85rem;
    box-shadow: 0 0.125rem 0.5rem rgba(15, 23, 42, 0.06);
    border-top: 3px solid #64748b;
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

.trip-save-confirm-table thead th {
    background: #f8fafc;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
}

.trip-save-confirm-table td {
    font-size: 0.85rem;
    vertical-align: middle;
}

.trip-save-confirm-table__row--changed {
    background: #fffbeb;
}

.trip-save-confirm-table__row--removed {
    background: #fef2f2;
}

.trip-save-confirm-expense-changes {
    margin-top: 0.35rem;
    font-size: 0.75rem;
}

.trip-save-confirm-expense-changes__item {
    margin-top: 0.15rem;
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
</style>
