<template>
    <AppModal
        :show="show"
        :size="size"
        :loading="loading"
        :dismissible="true"
        :close-on-escape="true"
        :show-header="false"
        :content-class="['app-confirm-modal__content', 'border-0', 'shadow-lg', contentClass]"
        :body-class="['p-4 pt-5', bodyClass]"
        :footer-class="'app-modal__footer--center border-0 gap-2'"
        @close="onCancel"
    >
        <button
            type="button"
            class="btn-close app-confirm-modal__close"
            aria-label="Close"
            :disabled="loading"
            @click="onCancel"
        ></button>

        <div class="text-center mb-3">
            <div class="app-confirm-modal__icon" :class="iconClass">
                <i :class="icon"></i>
            </div>
            <h5 class="app-confirm-modal__title mb-1">
                <slot name="title">{{ title }}</slot>
            </h5>
            <p v-if="message || $slots.message" class="text-muted small mb-0">
                <slot name="message">{{ message }}</slot>
            </p>
        </div>

        <slot />

        <template #footer>
            <button
                type="button"
                class="btn btn-outline-secondary px-4"
                :disabled="loading"
                @click="onCancel"
            >
                {{ cancelLabel }}
            </button>
            <AppButton
                v-if="showConfirm"
                :variant="confirmVariant"
                :label="loading ? loadingLabel : confirmLabel"
                :loading="loading"
                :disabled="confirmDisabled"
                class="px-4 fw-semibold"
                :icon="confirmIcon"
                @click="$emit('confirm')"
            />
            <AppButton
                v-if="secondaryConfirmLabel"
                :variant="secondaryConfirmVariant"
                :label="loading ? loadingLabel : secondaryConfirmLabel"
                :loading="loading"
                :disabled="confirmDisabled"
                class="px-4 fw-semibold"
                :icon="secondaryConfirmIcon"
                @click="$emit('secondary-confirm')"
            />
        </template>
    </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import AppButton from './AppButton.vue';
import AppModal from './AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    size: {
        type: String,
        default: 'lg',
        validator: (value) => ['lg', 'xl'].includes(value),
    },
    loading: { type: Boolean, default: false },
    icon: { type: String, default: 'bi bi-question-circle' },
    iconTone: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'danger', 'warning', 'success'].includes(value),
    },
    cancelLabel: { type: String, default: 'Cancel' },
    confirmLabel: { type: String, default: 'Confirm' },
    secondaryConfirmLabel: { type: String, default: '' },
    loadingLabel: { type: String, default: 'Processing…' },
    confirmVariant: { type: String, default: 'primary' },
    secondaryConfirmVariant: { type: String, default: 'primary' },
    confirmIcon: { type: String, default: '' },
    secondaryConfirmIcon: { type: String, default: '' },
    confirmDisabled: { type: Boolean, default: false },
    showConfirm: { type: Boolean, default: true },
    contentClass: { type: [String, Object, Array], default: '' },
    bodyClass: { type: [String, Object, Array], default: '' },
});

const emit = defineEmits(['cancel', 'confirm', 'secondary-confirm']);

const iconClass = computed(() => {
    const tones = {
        primary: '',
        danger: 'app-confirm-modal__icon--danger',
        warning: 'app-confirm-modal__icon--warning',
        success: 'app-confirm-modal__icon--success',
    };

    return tones[props.iconTone] ?? '';
});

function onCancel() {
    if (props.loading) {
        return;
    }

    emit('cancel');
}
</script>
