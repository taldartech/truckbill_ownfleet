export function formatPlainMeterValue(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const reading = Number(value);

    if (!Number.isFinite(reading)) {
        return null;
    }

    return Number.isInteger(reading) ? String(reading) : reading.toFixed(2).replace(/\.?0+$/, '');
}

export function formatMeterKm(value, { fallback = '—' } = {}) {
    const formatted = formatPlainMeterValue(value);

    if (formatted === null) {
        return fallback;
    }

    return `${formatted} KM`;
}
