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
