<template>
    <Teleport to="body">
        <div
            v-if="show"
            data-app-modal
            class="modal fade show app-modal-overlay"
            :class="{
                'app-modal-overlay--dismissible': dismissible && !loading,
                'app-modal-overlay--elevated': elevated,
            }"
            tabindex="-1"
            role="dialog"
            :aria-modal="true"
            :aria-labelledby="titleId"
            @click.self="onBackdropClick"
        >
            <div
                class="modal-dialog app-modal"
                :class="[
                    sizeClass,
                    { 'modal-dialog-centered': centered },
                ]"
                role="document"
                @click.stop
            >
                <div class="modal-content app-modal__content" :class="contentClass">
                    <div
                        v-if="showHeader"
                        class="modal-header app-modal__header"
                        :class="{ 'bg-gradient-primary': headerGradient }"
                    >
                        <slot name="header">
                            <h5 :id="titleId" class="modal-title mb-0">
                                <slot name="title">{{ title }}</slot>
                            </h5>
                        </slot>
                        <button
                            type="button"
                            class="btn-close"
                            :class="{ 'btn-close-white': headerGradient }"
                            aria-label="Close"
                            :disabled="loading"
                            @click="requestClose"
                        ></button>
                    </div>

                    <form
                        v-if="asForm"
                        class="app-modal__form"
                        novalidate
                        @submit.prevent="$emit('submit')"
                    >
                        <div class="app-modal__shell">
                            <div class="app-modal__body" :class="bodyClass">
                                <div
                                    v-if="formError"
                                    class="alert alert-danger py-2 mb-2 small"
                                    role="alert"
                                >
                                    {{ formError }}
                                </div>
                                <slot />
                            </div>
                            <div v-if="$slots.footer" class="app-modal__footer" :class="footerClass">
                                <slot name="footer" />
                            </div>
                        </div>
                    </form>

                    <div v-else class="app-modal__shell">
                        <div class="app-modal__body" :class="bodyClass">
                            <div
                                v-if="formError"
                                class="alert alert-danger py-2 mb-2 small"
                                role="alert"
                            >
                                {{ formError }}
                            </div>
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="app-modal__footer" :class="footerClass">
                            <slot name="footer" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, useId, watch } from 'vue';
import { useBodyScrollLock } from '../../composables/useBodyScrollLock';
import './appModal.css';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: {
        type: String,
        default: 'lg',
        validator: (value) => ['lg', 'xl'].includes(value),
    },
    loading: { type: Boolean, default: false },
    formError: { type: String, default: '' },
    centered: { type: Boolean, default: true },
    dismissible: { type: Boolean, default: false },
    closeOnEscape: { type: Boolean, default: false },
    headerGradient: { type: Boolean, default: true },
    showHeader: { type: Boolean, default: true },
    asForm: { type: Boolean, default: false },
    contentClass: { type: [String, Object, Array], default: '' },
    bodyClass: { type: [String, Object, Array], default: '' },
    footerClass: { type: [String, Object, Array], default: '' },
    elevated: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const titleId = useId();

const sizeClass = computed(() => (props.size === 'xl' ? 'app-modal--xl' : 'app-modal--lg'));

const showVisible = computed(() => props.show);

useBodyScrollLock(showVisible);

function onDocumentKeydown(event) {
    if (event.key !== 'Escape') {
        return;
    }

    onEscape(event);
}

watch(
    () => props.show,
    (visible) => {
        if (typeof document === 'undefined') {
            return;
        }

        if (visible) {
            document.addEventListener('keydown', onDocumentKeydown);
        } else {
            document.removeEventListener('keydown', onDocumentKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('keydown', onDocumentKeydown);
    }
});

function requestClose() {
    if (props.loading) {
        return;
    }

    emit('close');
}

function onBackdropClick() {
    if (!props.dismissible) {
        return;
    }

    requestClose();
}

function onEscape(event) {
    if (!props.closeOnEscape) {
        return;
    }

    event.preventDefault();
    requestClose();
}
</script>
