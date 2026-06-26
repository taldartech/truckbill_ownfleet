export function logApiError(error) {
    const data = error?.response?.data;

    if (!import.meta.env.DEV || !data) {
        return;
    }

    console.error('[API Error]', {
        status: error.response?.status,
        url: error.config?.url,
        message: data.message,
        debug: data.debug ?? null,
        errors: data.errors ?? null,
    });

    if (data.debug) {
        console.error('[API Debug]', data.debug);
    }
}

/**
 * @returns {{ message: string, fieldErrors: Record<string, string[]>, debug: object|null }}
 */
export function parseApiError(error, fallback = 'Something went wrong. Please try again.') {
    logApiError(error);

    const status = error?.response?.status;
    const data = error?.response?.data ?? {};
    const debug = data.debug ?? null;

    if (status === 422) {
        const fieldErrors = data.errors ?? {};
        const firstFieldMessage = Object.values(fieldErrors)
            .flat()
            .find((value) => typeof value === 'string' && value.length > 0);

        return {
            message: firstFieldMessage ?? data.message ?? fallback,
            fieldErrors,
            debug,
        };
    }

    if (typeof data.message === 'string' && data.message.length > 0) {
        return {
            message: data.message,
            fieldErrors: {},
            debug,
        };
    }

    if (status === 419) {
        return {
            message: 'Your session has expired. Please refresh the page and sign in again.',
            fieldErrors: {},
            debug,
        };
    }

    if (status === 403) {
        return {
            message: 'You do not have permission to perform this action.',
            fieldErrors: {},
            debug,
        };
    }

    return {
        message: fallback,
        fieldErrors: {},
        debug,
    };
}

export function apiErrorToastMessage(error, fallback = 'Something went wrong. Please try again.') {
    return parseApiError(error, fallback).message;
}
