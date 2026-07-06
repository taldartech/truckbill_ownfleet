import { onBeforeUnmount, onMounted } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';

export function useUnsavedFormGuard(isDirty, message = 'You have unsaved changes. Leave without saving?') {
    function handleBeforeUnload(event) {
        if (!isDirty.value) {
            return;
        }

        event.preventDefault();
        event.returnValue = message;
    }

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);
    });

    onBeforeRouteLeave((_to, _from, next) => {
        if (!isDirty.value) {
            next();

            return;
        }

        if (window.confirm(message)) {
            next();
        } else {
            next(false);
        }
    });
}
