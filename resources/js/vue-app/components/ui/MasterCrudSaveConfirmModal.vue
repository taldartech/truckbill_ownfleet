<template>
    <AppConfirmModal
        v-if="summary"
        :show="show"
        :title="summary.title"
        :message="summary.message"
        icon="bi bi-clipboard-check"
        :loading="saving"
        :confirm-label="summary.confirmLabel"
        loading-label="Saving…"
        confirm-variant="success"
        confirm-icon="bi bi-check-lg"
        @cancel="$emit('cancel')"
        @confirm="$emit('confirm')"
    >
        <div class="app-confirm-banner mb-3">
            <i class="bi bi-info-circle"></i>
            <span>Review {{ summary.entityLabel }} Details</span>
        </div>

        <div v-if="summary.changeSummary" class="master-crud-save-confirm-changes mb-3">
            <i class="bi bi-pencil-square"></i>
            <span>
                {{ summary.isCreate ? 'Setting:' : 'Updating:' }}
                <strong>{{ summary.changeSummary }}</strong>
            </span>
        </div>

        <div class="app-confirm-card">
            <div class="app-confirm-card__header">
                <i class="bi bi-card-list"></i>
                <span>Details</span>
            </div>
            <div class="app-confirm-card__body">
                <div
                    v-for="field in summary.fields"
                    :key="field.label"
                    class="app-confirm-row"
                    :class="{ 'master-crud-save-confirm-row--changed': field.changed }"
                >
                    <span class="app-confirm-row__label">{{ field.label }}</span>
                    <div class="text-end">
                        <span v-if="!field.changed" class="app-confirm-row__value">{{ field.value }}</span>
                        <div v-else class="master-crud-save-confirm-row__change">
                            <span class="text-muted small">{{ field.previousValue }}</span>
                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                            <strong>{{ field.value }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppConfirmModal>
</template>

<script setup>
import AppConfirmModal from './AppConfirmModal.vue';

defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    saving: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.master-crud-save-confirm-changes {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.75rem;
    background: rgba(255, 193, 7, 0.12);
    color: #856404;
    font-size: 0.9rem;
    font-weight: 600;
}

.master-crud-save-confirm-row--changed {
    background: rgba(255, 193, 7, 0.06);
    margin: 0 -0.9rem;
    padding-left: 0.9rem;
    padding-right: 0.9rem;
    border-radius: 0.35rem;
}

.master-crud-save-confirm-row__change {
    font-size: 0.85rem;
}
</style>
