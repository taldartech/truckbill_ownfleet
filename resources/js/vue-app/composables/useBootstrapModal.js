import { onBeforeUnmount } from 'vue';
import { cleanupBootstrapModalArtifacts, getBootstrapModal } from '../utils/bootstrapModal';

/**
 * Manage a single Bootstrap 5 modal instance for a Vue-controlled modal element.
 *
 * @param {import('vue').Ref<HTMLElement|null>} modalElementRef
 * @param {{
 *   onHidden?: () => void,
 *   backdrop?: boolean | 'static',
 *   focus?: boolean,
 *   keyboard?: boolean,
 * }} [options]
 */
export function useBootstrapModal(modalElementRef, options = {}) {
    const { onHidden, backdrop = true, focus = true, keyboard = true } = options;

    let instance = null;
    let hiddenListener = null;

    function onHiddenEvent() {
        onHidden?.();
        cleanupBootstrapModalArtifacts();
    }

    function ensureInstance() {
        const Modal = getBootstrapModal();
        const element = modalElementRef.value;

        if (!Modal || !element) {
            return null;
        }

        if (!instance) {
            instance = Modal.getOrCreateInstance(element, { backdrop, focus, keyboard });

            hiddenListener = onHiddenEvent;
            element.addEventListener('hidden.bs.modal', hiddenListener);
        }

        return instance;
    }

    function show() {
        ensureInstance()?.show();
    }

    function hide() {
        ensureInstance()?.hide();
    }

    onBeforeUnmount(() => {
        const element = modalElementRef.value;

        if (element && hiddenListener) {
            element.removeEventListener('hidden.bs.modal', hiddenListener);
        }

        instance?.dispose();
        instance = null;
        hiddenListener = null;
        cleanupBootstrapModalArtifacts();
    });

    return { show, hide };
}
