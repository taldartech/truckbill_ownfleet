<template>
    <div class="app-toast-host position-fixed top-0 end-0 p-3" style="z-index: 1090">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="toast show align-items-center border-0 mb-2"
            :class="toastClass(toast.type)"
            role="alert"
        >
            <div class="d-flex">
                <div class="toast-body">{{ toast.message }}</div>
                <button
                    type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    aria-label="Close"
                    @click="dismiss(toast.id)"
                ></button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useAppToast } from '../../composables/useAppToast';

const { toasts, dismiss } = useAppToast();

function toastClass(type) {
    if (type === 'danger') {
        return 'text-bg-danger';
    }

    if (type === 'warning') {
        return 'text-bg-warning';
    }

    if (type === 'info') {
        return 'text-bg-info';
    }

    return 'text-bg-success';
}
</script>
