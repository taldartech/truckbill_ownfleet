<template>
    <AppModal
        :show="show"
        :size="size"
        :loading="loading"
        :dismissible="!loading"
        :close-on-escape="!loading"
        elevated
        header-gradient
        :content-class="['tyre-action-confirm-modal__content', 'border-0', 'shadow-lg']"
        body-class="tyre-action-confirm-modal__body p-4"
        footer-class="app-modal__footer--center border-0 gap-2"
        @close="onCancel"
    >
        <template #header>
            <div class="tyre-action-confirm-modal__header-main">
                <h5 class="modal-title mb-0 text-white d-flex align-items-center gap-2">
                    <i :class="headerIcon"></i>
                    {{ title }}
                </h5>
                <div v-if="hasHeaderMeta" class="tyre-action-confirm-modal__header-meta">
                    <span class="tyre-action-confirm-modal__header-meta-item">
                        <span class="tyre-action-confirm-modal__header-label">Tyre Serial</span>
                        <span class="tyre-action-confirm-modal__header-value">{{ tyreSerial || '—' }}</span>
                    </span>
                    <span v-if="showTruck" class="tyre-action-confirm-modal__header-meta-item">
                        <span class="tyre-action-confirm-modal__header-label">Truck Number</span>
                        <RouterLink
                            v-if="vehicleId && truckNumber && truckNumber !== '—'"
                            :to="{ name: 'vehicles.tyres', params: { vehicleId } }"
                            class="tyre-action-confirm-modal__header-value tyre-action-confirm-modal__header-value--link"
                            @click.stop
                        >
                            {{ truckNumber }}
                        </RouterLink>
                        <span v-else class="tyre-action-confirm-modal__header-value">{{ truckNumber || '—' }}</span>
                    </span>
                    <span v-if="position" class="tyre-action-confirm-modal__header-meta-item">
                        <span class="tyre-action-confirm-modal__header-label">Position</span>
                        <span class="tyre-action-confirm-modal__header-value">{{ position }}</span>
                    </span>
                </div>
            </div>
        </template>

        <div class="text-center mb-3">
            <div class="app-confirm-modal__icon" :class="iconClass">
                <i :class="bodyIcon"></i>
            </div>
            <p v-if="message" class="text-muted small mb-0">{{ message }}</p>
        </div>

        <TripStatusChangeUpdatesPanel
            v-if="items?.length"
            class="mb-0 text-start"
            :items="items"
        />

        <template #footer>
            <button type="button" class="btn btn-outline-secondary px-4" :disabled="loading" @click="onCancel">
                {{ cancelLabel }}
            </button>
            <AppButton
                :variant="confirmVariant"
                :label="loading ? loadingLabel : confirmLabel"
                :loading="loading"
                class="px-4 fw-semibold"
                icon="bi bi-check-lg"
                @click="$emit('confirm')"
            />
        </template>
    </AppModal>
</template>

<script setup>
import { computed } from 'vue';
import TripStatusChangeUpdatesPanel from '../trips/TripStatusChangeUpdatesPanel.vue';
import AppButton from '../ui/AppButton.vue';
import AppModal from '../ui/AppModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm Action' },
    message: { type: String, default: '' },
    size: {
        type: String,
        default: 'lg',
        validator: (value) => ['lg', 'xl'].includes(value),
    },
    loading: { type: Boolean, default: false },
    confirmLabel: { type: String, default: 'Yes, Confirm' },
    loadingLabel: { type: String, default: 'Saving…' },
    confirmVariant: { type: String, default: 'success' },
    iconTone: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'danger', 'warning', 'success'].includes(value),
    },
    tyreSerial: { type: String, default: '' },
    truckNumber: { type: String, default: '' },
    vehicleId: { type: [String, Number], default: '' },
    position: { type: String, default: '' },
    showTruck: { type: Boolean, default: true },
    items: { type: Array, default: () => [] },
    cancelLabel: { type: String, default: 'Cancel' },
});

const emit = defineEmits(['cancel', 'confirm']);

const hasHeaderMeta = computed(() => Boolean(props.tyreSerial || (props.showTruck && props.truckNumber) || props.position));

const headerIcon = computed(() => {
    if (props.iconTone === 'danger') {
        return 'bi bi-exclamation-circle';
    }

    if (props.iconTone === 'warning') {
        return 'bi bi-arrow-repeat';
    }

    return 'bi bi-check-circle';
});

const bodyIcon = computed(() => {
    if (props.iconTone === 'danger') {
        return 'bi bi-question-circle';
    }

    if (props.iconTone === 'warning') {
        return 'bi bi-question-circle';
    }

    return 'bi bi-question-circle';
});

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

<style scoped>
.tyre-action-confirm-modal__header-main {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.tyre-action-confirm-modal__header-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 1rem;
    font-size: 0.75rem;
    line-height: 1.3;
    color: rgba(255, 255, 255, 0.82);
}

.tyre-action-confirm-modal__header-meta-item {
    display: inline-flex;
    align-items: baseline;
    gap: 0.3rem;
    min-width: 0;
}

.tyre-action-confirm-modal__header-label {
    opacity: 0.9;
}

.tyre-action-confirm-modal__header-label::after {
    content: ':';
}

.tyre-action-confirm-modal__header-value {
    font-weight: 700;
    color: #fff;
}

.tyre-action-confirm-modal__header-value--link {
    color: #fff;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.tyre-action-confirm-modal__header-value--link:hover {
    color: #fff;
    opacity: 0.92;
}
</style>
