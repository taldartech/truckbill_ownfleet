export function getBootstrapModal() {
    return typeof window !== 'undefined' ? (window.bootstrap?.Modal ?? null) : null;
}

export function countOpenBootstrapModals() {
    return document.querySelectorAll('.modal.show:not([data-app-modal])').length;
}

/**
 * Remove orphaned Bootstrap modal artifacts when no modal is visibly open.
 */
export function cleanupBootstrapModalArtifacts() {
    if (countOpenBootstrapModals() > 0) {
        return;
    }

    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

let cleanupInitialized = false;

/**
 * Register a document-level listener so every modal close path cleans up stray backdrops/body lock.
 */
export function initBootstrapModalCleanup() {
    if (typeof document === 'undefined' || cleanupInitialized) {
        return;
    }

    cleanupInitialized = true;
    document.addEventListener('hidden.bs.modal', cleanupBootstrapModalArtifacts);
}
