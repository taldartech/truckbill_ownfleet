import { ref } from 'vue';
import { extractValidationErrors } from '../utils/formErrors';

export function useApiFormErrors() {
    const fieldErrors = ref({});
    const formError = ref('');

    function clearErrors() {
        fieldErrors.value = {};
        formError.value = '';
    }

    /**
     * @returns {boolean} true when validation errors were applied to the form
     */
    function applyApiError(error, fallbackMessage = 'Unable to save. Please check the form and try again.') {
        const { fieldErrors: errors, message } = extractValidationErrors(error);

        if (Object.keys(errors).length > 0 || message) {
            fieldErrors.value = errors;
            formError.value = message ?? fallbackMessage;

            return true;
        }

        return false;
    }

    return {
        fieldErrors,
        formError,
        clearErrors,
        applyApiError,
    };
}
