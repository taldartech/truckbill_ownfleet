const GST_NUMBER_PATTERN = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
const PAN_NUMBER_PATTERN = /^[A-Z]{5}[0-9]{4}[A-Z]$/;

export const GST_NUMBER_LENGTH = 15;
export const PAN_NUMBER_LENGTH = 10;

export const GST_NUMBER_HINT = 'e.g. 27AAAAA0000A1Z5';
export const PAN_NUMBER_HINT = 'e.g. ABCDE1234F';

export const GST_NUMBER_ERROR =
    'Please enter a valid GST number (e.g. 27AAAAA0000A1Z5).';
export const PAN_NUMBER_REQUIRED_ERROR = 'PAN number is required.';
export const PAN_NUMBER_ERROR = 'Please enter a valid PAN number (e.g. ABCDE1234F).';

export function normalizeTaxIdentity(value, maxLength = 50) {
    return String(value ?? '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, maxLength);
}

export function normalizeGstNumber(value) {
    return normalizeTaxIdentity(value, GST_NUMBER_LENGTH);
}

export function normalizePanNumber(value) {
    return normalizeTaxIdentity(value, PAN_NUMBER_LENGTH);
}

export function isValidGstNumber(value) {
    const normalized = normalizeGstNumber(value);

    return normalized === '' || GST_NUMBER_PATTERN.test(normalized);
}

export function isValidPanNumber(value) {
    const normalized = normalizePanNumber(value);

    return normalized !== '' && PAN_NUMBER_PATTERN.test(normalized);
}

export function getGstNumberError(value) {
    const normalized = normalizeGstNumber(value);

    if (normalized === '') {
        return null;
    }

    return isValidGstNumber(normalized) ? null : GST_NUMBER_ERROR;
}

export function getPanNumberError(value, { required = false } = {}) {
    const normalized = normalizePanNumber(value);

    if (normalized === '') {
        return required ? PAN_NUMBER_REQUIRED_ERROR : null;
    }

    return isValidPanNumber(normalized) ? null : PAN_NUMBER_ERROR;
}
