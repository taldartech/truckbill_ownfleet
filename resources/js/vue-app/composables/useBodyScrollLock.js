import { onBeforeUnmount, watch } from 'vue';

let lockCount = 0;

function lockBodyScroll() {
    if (typeof document === 'undefined') {
        return;
    }

    lockCount += 1;

    if (lockCount === 1) {
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }
}

function releaseBodyScrollLock() {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
}

function unlockBodyScroll() {
    if (typeof document === 'undefined') {
        return;
    }

    lockCount = Math.max(0, lockCount - 1);

    if (lockCount === 0) {
        releaseBodyScrollLock();
    }
}

/**
 * Lock page scroll while a modal (or overlay) is open. Reference-counted for nested use.
 *
 * @param {import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>} visibleRef
 */
export function useBodyScrollLock(visibleRef) {
    watch(
        visibleRef,
        (visible, wasVisible) => {
            if (visible) {
                lockBodyScroll();
            } else if (wasVisible !== undefined) {
                unlockBodyScroll();
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        if (visibleRef.value) {
            unlockBodyScroll();
        }
    });
}
