export function formatMoney(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

/** Short INR label for charts and dense cards — e.g. 1250000 → "₹12.5L", 32000000 → "₹3.2Cr". */
export function formatMoneyCompact(value) {
    const amount = Number(value ?? 0);
    const absolute = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';

    if (absolute >= 10000000) {
        return `${sign}₹${trimDecimal(absolute / 10000000)}Cr`;
    }

    if (absolute >= 100000) {
        return `${sign}₹${trimDecimal(absolute / 100000)}L`;
    }

    if (absolute >= 1000) {
        return `${sign}₹${trimDecimal(absolute / 1000)}K`;
    }

    return `${sign}₹${Math.round(absolute)}`;
}

function trimDecimal(value) {
    return Number(value.toFixed(1)).toString();
}
