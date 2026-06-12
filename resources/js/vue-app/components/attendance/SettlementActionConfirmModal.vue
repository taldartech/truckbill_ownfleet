<template>
    <div
        v-if="show && config"
        class="modal fade show d-block settlement-action-confirm-modal"
        tabindex="-1"
        style="position: fixed; inset: 0; z-index: 1060; background: rgba(15, 23, 42, 0.55)"
        @click.self="$emit('cancel')"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content settlement-action-confirm-modal__content border-0 shadow-lg">
                <button
                    type="button"
                    class="btn-close settlement-action-confirm-modal__close"
                    aria-label="Close"
                    @click="$emit('cancel')"
                ></button>

                <div class="modal-body p-4 pt-5">
                    <div class="text-center mb-3">
                        <div
                            class="settlement-action-confirm-modal__icon"
                            :class="`settlement-action-confirm-modal__icon--${config.iconTone}`"
                        >
                            <i class="bi" :class="config.icon"></i>
                        </div>
                        <h5 class="settlement-action-confirm-modal__title mb-1">{{ config.title }}</h5>
                        <p class="text-muted small mb-0">{{ config.message }}</p>
                    </div>

                    <div
                        v-if="config.banner"
                        class="settlement-action-confirm-banner mb-3"
                        :class="`settlement-action-confirm-banner--${config.bannerTone}`"
                    >
                        <i class="bi" :class="config.bannerIcon"></i>
                        <span>{{ config.banner }}</span>
                    </div>

                    <div class="settlement-action-confirm-card">
                        <div class="settlement-action-confirm-card__header">
                            <i class="bi bi-calendar3"></i>
                            <span>{{ config.monthLabel }}</span>
                        </div>

                        <div class="settlement-action-confirm-card__body">
                            <div
                                v-for="(stat, index) in config.stats"
                                :key="`${stat.label}-${index}`"
                                class="settlement-action-confirm-stat"
                            >
                                <span class="text-muted">{{ stat.label }}</span>
                                <strong>{{ stat.value }}</strong>
                            </div>
                            <div v-if="config.emptyNote" class="text-muted small mb-0">
                                {{ config.emptyNote }}
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
                        class="btn px-4 fw-semibold"
                        :class="config.confirmClass"
                        :disabled="processing"
                        @click="$emit('confirm')"
                    >
                        <span
                            v-if="processing"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        ></span>
                        <i v-else class="bi me-1" :class="config.confirmIcon"></i>
                        {{ config.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: { type: Boolean, default: false },
    config: { type: Object, default: null },
    processing: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.settlement-action-confirm-modal__content {
    border-radius: 1rem;
    overflow: hidden;
}

.settlement-action-confirm-modal__close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 2;
}

.settlement-action-confirm-modal__icon {
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

.settlement-action-confirm-modal__icon--info {
    background: rgba(45, 140, 255, 0.12);
    color: var(--brand-accent-blue, #2d8cff);
}

.settlement-action-confirm-modal__icon--warning {
    background: rgba(255, 193, 7, 0.12);
    color: #856404;
}

.settlement-action-confirm-modal__icon--danger {
    background: rgba(220, 53, 69, 0.12);
    color: #dc3545;
}

.settlement-action-confirm-modal__title {
    font-weight: 700;
    color: #1e293b;
}

.settlement-action-confirm-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
}

.settlement-action-confirm-banner--success {
    background: rgba(25, 135, 84, 0.08);
    color: #146c43;
}

.settlement-action-confirm-banner--warning {
    background: rgba(255, 193, 7, 0.12);
    color: #856404;
}

.settlement-action-confirm-banner--danger {
    background: rgba(220, 53, 69, 0.08);
    color: #b02a37;
}

.settlement-action-confirm-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    overflow: hidden;
}

.settlement-action-confirm-card__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    color: #334155;
}

.settlement-action-confirm-card__body {
    padding: 0.85rem 1rem;
}

.settlement-action-confirm-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.35rem 0;
    font-size: 0.9rem;
}

.settlement-action-confirm-stat + .settlement-action-confirm-stat {
    border-top: 1px solid #f1f5f9;
}
</style>
