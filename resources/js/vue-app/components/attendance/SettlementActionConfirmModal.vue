<template>
    <AppConfirmModal
        v-if="config"
        :show="show"
        :title="config.title"
        :message="config.message"
        :icon="config.icon"
        :icon-tone="iconTone"
        :loading="processing"
        :confirm-label="config.confirmLabel"
        loading-label="Processing…"
        :confirm-variant="confirmVariant"
        :confirm-icon="config.confirmIcon"
        @cancel="$emit('cancel')"
        @confirm="$emit('confirm')"
    >
        <div
            v-if="config.banner"
            class="app-confirm-banner mb-3"
            :class="bannerClass"
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
    </AppConfirmModal>
</template>

<script setup>
import { computed } from 'vue';
import AppConfirmModal from '../ui/AppConfirmModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    config: { type: Object, default: null },
    processing: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);

const confirmVariant = computed(() => {
    const confirmClass = props.config?.confirmClass ?? '';

    if (confirmClass.includes('success')) {
        return 'success';
    }

    if (confirmClass.includes('warning')) {
        return 'warning';
    }

    if (confirmClass.includes('danger')) {
        return 'danger';
    }

    return 'primary';
});

const iconTone = computed(() => {
    const tone = props.config?.iconTone ?? 'primary';

    if (tone === 'info') {
        return 'primary';
    }

    return ['primary', 'danger', 'warning', 'success'].includes(tone) ? tone : 'primary';
});

const bannerClass = computed(() => {
    const tone = props.config?.bannerTone ?? 'success';

    return `settlement-action-confirm-banner--${tone}`;
});
</script>

<style scoped>
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
