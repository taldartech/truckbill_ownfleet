<template>
    <AppConfirmModal
        v-if="summary"
        :show="show"
        :title="summary.title"
        :message="summary.message"
        icon="bi bi-trash"
        icon-tone="danger"
        :loading="deleting"
        :confirm-label="summary.confirmLabel ?? 'Yes, Delete Record'"
        loading-label="Deleting…"
        confirm-variant="danger"
        confirm-icon="bi bi-trash"
        @cancel="$emit('cancel')"
        @confirm="$emit('confirm')"
    >
        <div class="app-confirm-card text-start">
            <div class="app-confirm-card__body">
                <div class="app-confirm-row">
                    <span class="app-confirm-row__label">Truck</span>
                    <span class="app-confirm-row__value">{{ summary.truck }}</span>
                </div>
                <div class="app-confirm-row">
                    <span class="app-confirm-row__label">Service Date</span>
                    <span class="app-confirm-row__value">{{ summary.serviceDate }}</span>
                </div>
                <div class="app-confirm-row">
                    <span class="app-confirm-row__label">Service Type</span>
                    <span class="app-confirm-row__value">{{ summary.serviceType }}</span>
                </div>
                <div class="app-confirm-row">
                    <span class="app-confirm-row__label">Provider</span>
                    <span class="app-confirm-row__value">{{ summary.provider }}</span>
                </div>
                <div class="app-confirm-row">
                    <span class="app-confirm-row__label">Cost</span>
                    <span class="app-confirm-row__value">₹{{ summary.cost }}</span>
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
    deleting: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>
