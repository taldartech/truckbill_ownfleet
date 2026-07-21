<template>
    <AppConfirmModal
        v-if="summary"
        :show="show"
        :title="summary.title"
        icon="bi bi-truck"
        icon-tone="primary"
        :confirm-label="summary.confirmLabel"
        confirm-variant="info"
        confirm-icon="bi bi-check-lg"
        @cancel="$emit('cancel')"
        @confirm="$emit('confirm')"
    >
        <div class="text-start trip-vehicle-select-confirm">
            <div class="app-confirm-banner trip-vehicle-select-confirm__notice mb-2">
                <i class="bi bi-info-circle"></i>
                <span>{{ summary.notice }}</span>
            </div>

            <div class="app-confirm-card">
                <div class="app-confirm-card__body">
                    <div
                        v-for="row in summary.truckSummary"
                        :key="row.label"
                        class="app-confirm-row"
                    >
                        <span class="app-confirm-row__label">{{ row.label }}</span>
                        <span class="app-confirm-row__value">{{ row.value }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppConfirmModal>
</template>

<script setup>
import AppConfirmModal from '../ui/AppConfirmModal.vue';

defineProps({
    show: { type: Boolean, default: false },
    summary: { type: Object, default: null },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.trip-vehicle-select-confirm__notice {
    font-size: 0.8125rem;
    padding: 0.5rem 0.75rem;
}
</style>
