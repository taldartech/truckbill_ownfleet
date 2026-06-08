export function normalizeMobile(value) {
    return String(value ?? '').replace(/\D/g, '').slice(0, 10);
}

export function isValidMobile(value) {
    const digits = normalizeMobile(value);

    return digits === '' || /^[0-9]{10}$/.test(digits);
}
