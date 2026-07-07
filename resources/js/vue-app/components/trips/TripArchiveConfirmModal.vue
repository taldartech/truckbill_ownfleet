<template>
    <AppConfirmModal
        v-if="summary"
        :show="show"
        :title="summary.title"
        :loading="archiving"
        icon="bi bi-archive"
        icon-tone="danger"
        size="xl"
        :confirm-label="summary.confirmLabel"
        :loading-label="summary.archivingLabel"
        confirm-variant="danger"
        confirm-icon="bi bi-archive"
        :confirm-disabled="!canConfirm"
        :show-confirm="summary.canArchive"
        :content-class="['app-confirm-modal__content', 'app-confirm-modal__content--compact', 'border-0', 'shadow-lg']"
        body-class="app-confirm-modal__body--compact"
        @cancel="$emit('cancel')"
        @confirm="onConfirm"
    >
        <div v-if="!summary.canArchive" class="alert alert-danger py-2 mb-0 text-start">
            {{ summary.blockReason }}
        </div>

        <div v-else class="text-start trip-archive-confirm">
            <div class="app-confirm-banner trip-archive-confirm__notice mb-2">
                <i class="bi bi-info-circle"></i>
                <span>{{ summary.permanentNotice }}</span>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <div class="app-confirm-card h-100 trip-archive-confirm__card">
                        <div class="app-confirm-card__section-title">Trip Summary</div>
                        <div class="app-confirm-card__body trip-archive-confirm__card-body">
                            <div
                                v-for="row in summary.tripSummary"
                                :key="row.label"
                                class="app-confirm-row trip-archive-confirm__row"
                            >
                                <span class="app-confirm-row__label">{{ row.label }}</span>
                                <span class="app-confirm-row__value">{{ row.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="summary.linkedRecordRows.length" class="col-md-6">
                    <div class="app-confirm-card h-100 trip-archive-confirm__card">
                        <div class="app-confirm-card__section-title">Linked Records</div>
                        <div class="app-confirm-card__body trip-archive-confirm__card-body">
                            <div
                                v-for="row in summary.linkedRecordRows"
                                :key="row.key"
                                class="app-confirm-row trip-archive-confirm__row"
                            >
                                <span class="app-confirm-row__label">
                                    <i v-if="row.icon" :class="row.icon" class="me-1"></i>
                                    {{ row.label }}
                                </span>
                                <span class="app-confirm-row__value">{{ row.value }}</span>
                            </div>
                            <div
                                v-if="summary.totalExpenseRow"
                                class="app-confirm-row trip-archive-confirm__row trip-archive-confirm__total-row"
                            >
                                <span class="app-confirm-row__label">
                                    <i class="bi bi-currency-rupee me-1"></i>
                                    {{ summary.totalExpenseRow.label }}
                                </span>
                                <span class="app-confirm-row__value trip-archive-confirm__total-value">
                                    {{ summary.totalExpenseRow.value }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="summary.hasFinancialWarning"
                class="trip-archive-confirm__warning-ack mb-2"
            >
                <div class="trip-archive-confirm__warning-text">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>{{ summary.financialWarningMessage }}</span>
                </div>
                <div class="form-check mb-0">
                    <input
                        id="trip-archive-financial-ack"
                        v-model="financialAcknowledged"
                        class="form-check-input"
                        type="checkbox"
                        :disabled="archiving"
                    />
                    <label class="form-check-label" for="trip-archive-financial-ack">
                        I understand financial records will be removed from active reports
                    </label>
                </div>
            </div>

            <div class="trip-archive-confirm__reason">
                <label class="form-label mb-1">Reason for Archive <span class="text-danger">*</span></label>
                <textarea
                    v-model="archiveReason"
                    class="form-control form-control-sm"
                    rows="2"
                    placeholder="Why is this trip being archived?"
                    :class="{ 'is-invalid': fieldErrors.archive_reason }"
                    :disabled="archiving"
                ></textarea>
                <div v-if="fieldErrors.archive_reason" class="invalid-feedback d-block">
                    {{ fieldErrors.archive_reason }}
                </div>
            </div>
        </div>
    </AppConfirmModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppConfirmModal from '../ui/AppConfirmModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    archiving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['cancel', 'confirm']);

const archiveReason = ref('');
const financialAcknowledged = ref(false);

const canConfirm = computed(() => {
    const reason = archiveReason.value.trim();

    if (reason.length < 10 || reason.length > 500) {
        return false;
    }

    if (props.summary?.hasFinancialWarning && !financialAcknowledged.value) {
        return false;
    }

    return true;
});

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            archiveReason.value = '';
            financialAcknowledged.value = false;
        }
    },
);

function onConfirm() {
    if (!canConfirm.value || props.archiving) {
        return;
    }

    emit('confirm', {
        archive_reason: archiveReason.value.trim(),
    });
}
</script>

<style scoped>
.trip-archive-confirm__notice {
    font-size: 0.8125rem;
    padding: 0.5rem 0.75rem;
}

.trip-archive-confirm__card-body {
    padding: 0.45rem 0.75rem 0.55rem;
}

.trip-archive-confirm__row {
    padding: 0.3rem 0;
}

.trip-archive-confirm__warning-ack {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.65rem;
    background: rgba(239, 68, 68, 0.06);
    border: 1px solid rgba(239, 68, 68, 0.18);
}

.trip-archive-confirm__warning-text {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--brand-danger-dark, #dc2626);
    font-size: 0.8125rem;
    font-weight: 600;
    flex: 1 1 220px;
}

.trip-archive-confirm__warning-text i {
    flex-shrink: 0;
}

.trip-archive-confirm__warning-ack .form-check-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
}

.trip-archive-confirm__total-row {
    margin-top: 0.15rem;
    padding-top: 0.35rem;
    border-top: 1px dashed #e2e8f0;
}

.trip-archive-confirm__total-value {
    color: var(--brand-danger, #ef4444);
}

.trip-archive-confirm__reason .form-label {
    font-size: 0.8125rem;
}
</style>
