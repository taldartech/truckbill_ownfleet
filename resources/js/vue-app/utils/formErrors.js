function sanitizeApiMessage(message) {
    if (typeof message !== 'string' || message.length === 0) {
        return null;
    }

    if (message.includes('Column not found') || message.includes('SQLSTATE')) {
        return 'Database update required. Run: php artisan migrate';
    }

    if (message.length > 280) {
        return 'Something went wrong. Please try again.';
    }

    return message;
}

export function extractValidationErrors(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 422) {
        return {
            fieldErrors: data?.errors ?? {},
            message: sanitizeApiMessage(data?.message),
        };
    }

    const message = sanitizeApiMessage(data?.message);

    if (message) {
        return {
            fieldErrors: {},
            message,
        };
    }

    return {
        fieldErrors: {},
        message: null,
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
