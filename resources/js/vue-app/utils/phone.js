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
