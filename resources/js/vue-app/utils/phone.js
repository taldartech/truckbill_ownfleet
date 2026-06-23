export function normalizeDigits(value, maxLength = null) {
    const digits = String(value ?? '').replace(/\D/g, '');

    return maxLength !== null ? digits.slice(0, maxLength) : digits;
}

export function normalizeMobile(value) {
    return normalizeDigits(value, 10);
}

export function isValidMobile(value) {
    const digits = normalizeMobile(value);

    return digits === '' || /^[0-9]{10}$/.test(digits);
}

const NUMERIC_INPUT_ALLOWED_KEYS = new Set([
    'Backspace',
    'Delete',
    'Tab',
    'Escape',
    'Enter',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
]);

export function allowNumericKeydown(event) {
    if (event.ctrlKey || event.metaKey) {
        return;
    }

    if (NUMERIC_INPUT_ALLOWED_KEYS.has(event.key)) {
        return;
    }

    if (/^[0-9]$/.test(event.key)) {
        return;
    }

    event.preventDefault();
}
