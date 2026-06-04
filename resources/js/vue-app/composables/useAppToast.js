import { readonly, ref } from 'vue';

const toasts = ref([]);

let nextId = 1;

function removeToast(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pushToast(message, type = 'success') {
    const id = nextId++;
    toasts.value.push({ id, message, type });

    window.setTimeout(() => removeToast(id), 5000);
}

export function useAppToast() {
    return {
        toasts: readonly(toasts),
        success(message) {
            pushToast(message, 'success');
        },
        error(message) {
            pushToast(message, 'danger');
        },
        warning(message) {
            pushToast(message, 'warning');
        },
        info(message) {
            pushToast(message, 'info');
        },
        dismiss: removeToast,
    };
}
