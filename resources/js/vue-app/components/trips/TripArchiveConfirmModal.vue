<template>
    <AppConfirmModal
        v-if="summary"
        :show="show"
        :title="summary.title"
        :loading="archiving"
        icon="bi bi-archive"
        icon-tone="danger"
        :confirm-label="summary.confirmLabel"
        :loading-label="summary.archivingLabel"
        confirm-variant="danger"
        confirm-icon="bi bi-archive"
        :confirm-disabled="!canConfirm"
        :show-confirm="summary.canArchive"
        @cancel="$emit('cancel')"
        @confirm="onConfirm"
    >
        <div v-if="!summary.canArchive" class="alert alert-danger py-2 mb-0 text-start">
            {{ summary.blockReason }}
        </div>

        <div v-else class="text-start">
            <div class="app-confirm-banner mb-3">
                <i class="bi bi-info-circle"></i>
                <span>{{ summary.permanentNotice }}</span>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="app-confirm-card h-100">
                        <div class="app-confirm-card__section-title">Trip Summary</div>
                        <div class="app-confirm-card__body">
                            <div
                                v-for="row in summary.tripSummary"
                                :key="row.label"
                                class="app-confirm-row"
                            >
                                <span class="app-confirm-row__label">{{ row.label }}</span>
                                <span class="app-confirm-row__value">{{ row.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="summary.linkedRecordRows.length" class="col-md-6">
                    <div class="app-confirm-card h-100">
                        <div class="app-confirm-card__section-title">Linked Records</div>
                        <div class="app-confirm-card__body">
                            <div
                                v-for="row in summary.linkedRecordRows"
                                :key="row.key"
                                class="app-confirm-row"
                            >
                                <span class="app-confirm-row__label">
                                    <i v-if="row.icon" :class="row.icon" class="me-1"></i>
                                    {{ row.label }}
                                </span>
                                <span class="app-confirm-row__value">{{ row.value }}</span>
                            </div>
                            <div
                                v-if="summary.totalExpenseRow"
                                class="app-confirm-row trip-archive-confirm__total-row"
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
                class="trip-archive-confirm__warning mb-3"
            >
                <i class="bi bi-exclamation-triangle"></i>
                <span>{{ summary.financialWarningMessage }}</span>
            </div>

            <div
                v-if="summary.hasFinancialWarning"
                class="trip-archive-confirm__ack mb-3"
            >
                <div class="form-check mb-0">
                    <input
                        id="trip-archive-financial-ack"
                        v-model="financialAcknowledged"
                        class="form-check-input"
                        type="checkbox"
                        :disabled="archiving"
                    />
                    <label class="form-check-label" for="trip-archive-financial-ack">
                        I understand this will permanently remove operational financial records
                    </label>
                </div>
            </div>

            <div>
                <label class="form-label">Reason for Archive <span class="text-danger">*</span></label>
                <textarea
                    v-model="archiveReason"
                    class="form-control"
                    rows="2"
                    placeholder="Why is this trip being archived? e.g. Duplicate trip created."
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
.trip-archive-confirm__warning {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    background: rgba(239, 68, 68, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: var(--brand-danger-dark, #dc2626);
    font-size: 0.85rem;
    line-height: 1.45;
}

.trip-archive-confirm__warning i {
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.trip-archive-confirm__ack {
    padding: 0.65rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #f8fafc;
}

.trip-archive-confirm__ack .form-check-label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
}

.trip-archive-confirm__total-row {
    margin-top: 0.35rem;
    padding-top: 0.55rem;
    border-top: 1px dashed #e2e8f0;
}

.trip-archive-confirm__total-value {
    color: var(--brand-danger, #ef4444);
}
</style>
