import { onMounted, onUnmounted } from 'vue';

/** @type {Map<string, Set<() => void>>} */
const scopeListeners = new Map();

/**
 * @param {string[]} scopes
 */
export function invalidateOperationalData(scopes = []) {
    scopes.forEach((scope) => {
        const listeners = scopeListeners.get(scope);

        if (!listeners) {
            return;
        }

        listeners.forEach((listener) => {
            listener();
        });
    });
}

/**
 * @param {string} scope
 * @param {() => void | Promise<void>} reloadFn
 */
export function useOperationalDataInvalidation(scope, reloadFn) {
    onMounted(() => {
        if (!scopeListeners.has(scope)) {
            scopeListeners.set(scope, new Set());
        }

        scopeListeners.get(scope).add(reloadFn);
    });

    onUnmounted(() => {
        scopeListeners.get(scope)?.delete(reloadFn);
    });
}
