export function extractValidationErrors(error) {
    if (error?.response?.status !== 422) {
        return {
            fieldErrors: {},
            message: null,
        };
    }

    return {
        fieldErrors: error.response.data?.errors ?? {},
        message: error.response.data?.message ?? null,
    };
}

/**
 * User-facing message for non-validation API failures.
 */
export function friendlyApiErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const raw = error?.response?.data?.message;

    if (typeof raw === 'string' && raw.includes('Column not found')) {
        return 'Database update required. Run: php artisan migrate';
    }

    if (typeof raw === 'string' && raw.length > 0 && raw.length < 280) {
        return raw;
    }

    if (error?.response?.status === 419) {
        return 'Your session has expired. Please refresh the page and sign in again.';
    }

    if (error?.response?.status === 403) {
        return 'You do not have permission to perform this action.';
    }

    return fallback;
}

export function fieldErrorMessage(fieldErrors, field) {
    const messages = fieldErrors?.[field];

    if (Array.isArray(messages)) {
        return messages[0] ?? null;
    }

    return typeof messages === 'string' ? messages : null;
}

export function hasFieldError(fieldErrors, field) {
    return Boolean(fieldErrorMessage(fieldErrors, field));
}

export function scrollToFirstFormError() {
    requestAnimationFrame(() => {
        const target = document.querySelector(
            '.trip-form .is-invalid, .trip-form .is-invalid-picker, .trip-form .alert-danger',
        );

        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}
