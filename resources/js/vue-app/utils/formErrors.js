import { apiErrorToastMessage, parseApiError } from './apiError';

function sanitizeApiMessage(message) {
    if (typeof message !== 'string' || message.length === 0) {
        return null;
    }

    if (message.includes('Column not found') || message.includes('SQLSTATE')) {
        return 'Database update required. Run: php artisan migrate';
    }

    if (message.length > 400) {
        return 'Something went wrong. Please try again.';
    }

    return message;
}

export { logApiError } from './apiError';

export function extractValidationErrors(error) {
    const parsed = parseApiError(error);
    const status = error?.response?.status;

    if (status === 422) {
        const fieldErrors = parsed.fieldErrors;
        const firstFieldMessage = Object.values(fieldErrors)
            .flat()
            .find((value) => typeof value === 'string' && value.length > 0);

        return {
            fieldErrors,
            message: firstFieldMessage ?? sanitizeApiMessage(parsed.message),
        };
    }

    const message = sanitizeApiMessage(parsed.message);

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
    return apiErrorToastMessage(error, fallback);
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
            '.trip-expenses-page .is-invalid, .trip-expenses-page .is-invalid-picker, .trip-expenses-page .expense-validation-summary, .trip-form .is-invalid, .trip-form .is-invalid-picker, .trip-form .alert-danger, .maintenance-form .is-invalid, .maintenance-form .is-invalid-picker, .maintenance-form .alert-danger',
        );

        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.focus?.();
    });
}

export function scrollToExpenseField(focusId) {
    requestAnimationFrame(() => {
        const target = document.getElementById(focusId);

        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.focus?.();
    });
}
