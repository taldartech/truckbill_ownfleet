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

/**
 * Dynamic INR formatter for dashboard KPI cards.
 * - Values < 1 Crore: Full Indian formatting (e.g. ₹19,000, ₹19,800, ₹46,96,094)
 * - Values >= 1 Crore (touching 100 Cr+): Compact crore notation (e.g. ₹1.14 Cr, ₹100 Cr)
 * Ensures large figures touching 100 Cr adjust dynamically inside cards.
 */
export function formatDynamicMoney(value) {
    const amount = Number(value ?? 0);
    const absolute = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';

    if (absolute >= 10000000) {
        const crores = absolute / 10000000;
        const maxDecimals = crores >= 100 ? 1 : 2;
        const formattedCr = new Intl.NumberFormat('en-IN', {
            maximumFractionDigits: maxDecimals,
        }).format(Number(crores.toFixed(maxDecimals)));

        return `${sign}₹${formattedCr} Cr`;
    }

    const hasDecimals = Math.abs(amount % 1) > 0.009;

    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: hasDecimals ? 1 : 0,
        maximumFractionDigits: 2,
    }).format(amount);
}

function trimDecimal(value, maxDecimals = 1) {
    return Number(value.toFixed(maxDecimals)).toString();
}
