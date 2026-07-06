<template>
    <div
        v-if="show && summary"
        class="modal fade show d-block trip-save-confirm-modal"
        tabindex="-1"
        style="background: rgba(15, 23, 42, 0.55)"
        @click.self="$emit('cancel')"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content trip-save-confirm-modal__content border-0 shadow-lg">
                <button
                    type="button"
                    class="btn-close trip-save-confirm-modal__close"
                    aria-label="Close"
                    @click="$emit('cancel')"
                ></button>

                <div class="modal-body p-4 pt-5 text-center">
                    <div class="trip-save-confirm-modal__icon trip-save-confirm-modal__icon--danger">
                        <i class="bi bi-trash"></i>
                    </div>
                    <h5 class="trip-save-confirm-modal__title mb-1">{{ summary.title }}</h5>
                    <p class="text-muted small mb-3">{{ summary.message }}</p>

                    <div class="trip-save-confirm-card text-start">
                        <div class="trip-save-confirm-card__body">
                            <div class="trip-save-confirm-row">
                                <span class="trip-save-confirm-row__label">Truck</span>
                                <span class="trip-save-confirm-row__value">{{ summary.truck }}</span>
                            </div>
                            <div class="trip-save-confirm-row">
                                <span class="trip-save-confirm-row__label">Service Date</span>
                                <span class="trip-save-confirm-row__value">{{ summary.serviceDate }}</span>
                            </div>
                            <div class="trip-save-confirm-row">
                                <span class="trip-save-confirm-row__label">Service Type</span>
                                <span class="trip-save-confirm-row__value">{{ summary.serviceType }}</span>
                            </div>
                            <div class="trip-save-confirm-row">
                                <span class="trip-save-confirm-row__label">Provider</span>
                                <span class="trip-save-confirm-row__value">{{ summary.provider }}</span>
                            </div>
                            <div class="trip-save-confirm-row">
                                <span class="trip-save-confirm-row__label">Cost</span>
                                <span class="trip-save-confirm-row__value">₹{{ summary.cost }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 justify-content-center gap-2 pb-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('cancel')">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger px-4 fw-semibold" :disabled="deleting" @click="$emit('confirm')">
                        <span
                            v-if="deleting"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        ></span>
                        <i v-else class="bi bi-trash me-1"></i>
                        {{ summary.confirmLabel ?? 'Yes, Delete Record' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    deleting: { type: Boolean, default: false },
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
    font-size: 2rem;
    line-height: 1;
}

.trip-save-confirm-modal__icon--danger {
    background: rgba(220, 53, 69, 0.12);
    color: #dc3545;
}

.trip-save-confirm-modal__title {
    font-weight: 700;
    color: #1e293b;
}

.trip-save-confirm-card {
    background: #fff;
    border-radius: 0.85rem;
    box-shadow: 0 0.125rem 0.5rem rgba(15, 23, 42, 0.06);
    border: 1px solid #e2e8f0;
}

.trip-save-confirm-card__body {
    padding: 0.75rem 0.9rem;
}

.trip-save-confirm-row {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.trip-save-confirm-row:last-child {
    border-bottom: 0;
}

.trip-save-confirm-row__label {
    color: #64748b;
    font-size: 0.8rem;
}

.trip-save-confirm-row__value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1e293b;
    text-align: right;
}
</style>
