import { ref } from 'vue';
import { apiErrorToastMessage, logApiError, parseApiError } from '../utils/apiError';

export function useApiError() {
    const lastError = ref(null);
    const debugInfo = ref(null);

    function handle(error, fallback = 'Unable to save. Please try again.') {
        const parsed = parseApiError(error, fallback);

        lastError.value = parsed;
        debugInfo.value = parsed.debug;

        return parsed;
    }

    function toastMessage(error, fallback = 'Unable to save. Please try again.') {
        return apiErrorToastMessage(error, fallback);
    }

    return {
        lastError,
        debugInfo,
        handle,
        toastMessage,
        logApiError,
    };
}
