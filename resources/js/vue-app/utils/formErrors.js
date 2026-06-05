export function extractValidationErrors(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 422) {
        return {
            fieldErrors: data?.errors ?? {},
            message: data?.message ?? null,
        };
    }

    if (typeof data?.message === 'string' && data.message.length > 0) {
        return {
            fieldErrors: {},
            message: data.message,
        };
    }

    return {
        fieldErrors: {},
        message: null,
    };
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
