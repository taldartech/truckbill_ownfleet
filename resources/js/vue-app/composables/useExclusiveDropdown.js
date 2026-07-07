let activeClose = null;
let escapeListenerAttached = false;

function handleEscape(event) {
    if (event.key !== 'Escape' || !activeClose) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    activeClose();
}

function ensureEscapeListener() {
    if (escapeListenerAttached || typeof document === 'undefined') {
        return;
    }

    document.addEventListener('keydown', handleEscape, true);
    escapeListenerAttached = true;
}

/**
 * Close any other open dropdown and mark this one as active.
 *
 * @param {() => void} close
 */
export function claimExclusiveDropdown(close) {
    if (activeClose && activeClose !== close) {
        activeClose();
    }

    activeClose = close;
    ensureEscapeListener();
}

/**
 * @param {() => void} close
 */
export function releaseExclusiveDropdown(close) {
    if (activeClose === close) {
        activeClose = null;
    }
}
