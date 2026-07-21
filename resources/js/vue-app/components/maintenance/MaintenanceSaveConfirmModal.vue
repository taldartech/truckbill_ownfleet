<template>
    <AppModal
        v-if="summary"
        :show="show"
        size="lg"
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
            <h5 class="app-confirm-modal__title mb-1">{{ summary.title }}</h5>
            <p class="text-muted small mb-0">{{ summary.message }}</p>
        </div>

        <div class="trip-save-confirm-banner mb-3">
            <i class="bi bi-info-circle"></i>
            <span>Review Maintenance Details</span>
        </div>

        <div v-if="summary.changeSummary" class="trip-save-confirm-changes mb-3">
            <i class="bi bi-pencil-square"></i>
            <span>
                Updating:
                <strong>{{ summary.changeSummary }}</strong>
            </span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="trip-save-confirm-card trip-save-confirm-card--primary h-100">
                    <div class="trip-save-confirm-card__header">
                        <i class="bi bi-truck"></i>
                        <span>Maintenance Information</span>
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
                                <span class="trip-save-confirm-row__value">{{ field.value }}</span>
                                <span v-if="field.changed" class="badge bg-warning text-dark ms-1">Updated</span>
                            </div>
                            <div v-if="field.changed" class="trip-save-confirm-row__prev">
                                <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong>{{ field.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="trip-save-confirm-card trip-save-confirm-card--info h-100">
                    <div class="trip-save-confirm-card__header">
                        <i class="bi bi-gear"></i>
                        <span>Service Information</span>
                    </div>
                    <div class="trip-save-confirm-card__body">
                        <div
                            v-for="field in summary.serviceFields"
                            :key="field.label"
                            class="trip-save-confirm-row"
                            :class="{ 'trip-save-confirm-row--changed': field.changed }"
                        >
                            <span class="trip-save-confirm-row__label">{{ field.label }}</span>
                            <div class="trip-save-confirm-row__value-wrap">
                                <span class="trip-save-confirm-row__value">{{ field.value }}</span>
                                <span v-if="field.changed" class="badge bg-warning text-dark ms-1">Updated</span>
                            </div>
                            <div v-if="field.changed" class="trip-save-confirm-row__prev">
                                <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong>{{ field.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="trip-save-confirm-card trip-save-confirm-card--warning h-100">
                    <div class="trip-save-confirm-card__header">
                        <i class="bi bi-currency-rupee"></i>
                        <span>Cost Details</span>
                    </div>
                    <div class="trip-save-confirm-card__body">
                        <div
                            v-for="field in summary.costFields"
                            :key="field.label"
                            class="trip-save-confirm-row"
                            :class="{ 'trip-save-confirm-row--changed': field.changed }"
                        >
                            <span class="trip-save-confirm-row__label">{{ field.label }}</span>
                            <div class="trip-save-confirm-row__value-wrap">
                                <span class="trip-save-confirm-row__value">{{ field.value }}</span>
                                <span v-if="field.changed" class="badge bg-warning text-dark ms-1">Updated</span>
                            </div>
                            <div v-if="field.changed" class="trip-save-confirm-row__prev">
                                <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong>{{ field.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="trip-save-confirm-card trip-save-confirm-card--route h-100">
                    <div class="trip-save-confirm-card__header">
                        <i class="bi bi-calendar-check"></i>
                        <span>Next Due Service</span>
                    </div>
                    <div class="trip-save-confirm-card__body">
                        <div
                            v-for="field in summary.nextDueFields"
                            :key="field.label"
                            class="trip-save-confirm-row"
                            :class="{ 'trip-save-confirm-row--changed': field.changed }"
                        >
                            <span class="trip-save-confirm-row__label">{{ field.label }}</span>
                            <div class="trip-save-confirm-row__value-wrap">
                                <span class="trip-save-confirm-row__value">{{ field.value }}</span>
                                <span v-if="field.changed" class="badge bg-warning text-dark ms-1">Updated</span>
                            </div>
                            <div v-if="field.changed" class="trip-save-confirm-row__prev">
                                <span class="trip-save-confirm-row__prev-value">{{ field.previousValue }}</span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong>{{ field.value }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="summary.notesField && (summary.notesField.changed || summary.notesField.value !== '—')"
                class="col-12"
            >
                <div class="trip-save-confirm-card h-100">
                    <div class="trip-save-confirm-card__header">
                        <i class="bi bi-chat-left-text"></i>
                        <span>Notes</span>
                    </div>
                    <div class="trip-save-confirm-card__body">
                        <div
                            class="trip-save-confirm-row"
                            :class="{ 'trip-save-confirm-row--changed': summary.notesField.changed }"
                        >
                            <span class="trip-save-confirm-row__label">{{ summary.notesField.label }}</span>
                            <div class="trip-save-confirm-row__value-wrap">
                                <span class="trip-save-confirm-row__value">{{ summary.notesField.value }}</span>
                                <span v-if="summary.notesField.changed" class="badge bg-warning text-dark ms-1">
                                    Updated
                                </span>
                            </div>
                            <div v-if="summary.notesField.changed" class="trip-save-confirm-row__prev">
                                <span class="trip-save-confirm-row__prev-value">
                                    {{ summary.notesField.previousValue }}
                                </span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <strong>{{ summary.notesField.value }}</strong>
                            </div>
                        </div>
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
                v-if="summary.showAddAnotherAction"
                type="button"
                class="btn px-4 fw-semibold"
                :class="summary.isCreate ? 'btn-success' : 'btn-warning'"
                :disabled="saving"
                @click="$emit('confirm', 'another')"
            >
                <span
                    v-if="saving && confirmMode === 'another'"
                    class="spinner-border spinner-border-sm me-1"
                    role="status"
                    aria-hidden="true"
                ></span>
                <i v-else class="bi bi-plus-circle me-1"></i>
                {{ summary.addAnotherLabel ?? 'Save & Add Another' }}
            </button>
            <button
                type="button"
                class="btn px-4"
                :class="summary.isCreate ? 'btn-outline-info' : 'btn-warning fw-semibold'"
                :disabled="saving"
                @click="$emit('confirm', 'index')"
            >
                <span
                    v-if="saving && confirmMode !== 'another'"
                    class="spinner-border spinner-border-sm me-1"
                    role="status"
                    aria-hidden="true"
                ></span>
                <i v-else class="bi bi-check-lg me-1"></i>
                {{ summary.confirmLabel ?? 'Yes, Save Record' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import AppModal from '../ui/AppModal.vue';

defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    confirmMode: { type: String, default: 'index' },
});

defineEmits(['cancel', 'confirm']);
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

.trip-save-confirm-card__header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.75rem 0.9rem 0.35rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
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
    padding: 0.45rem 0.5rem;
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

.trip-save-confirm-row__prev {
    width: 100%;
    font-size: 0.75rem;
    color: #64748b;
    text-align: right;
    margin-top: 0.15rem;
}

.trip-save-confirm-row__prev-value {
    text-decoration: line-through;
    color: #94a3b8;
}
</style>
