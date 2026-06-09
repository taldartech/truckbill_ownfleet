export const TRIP_LIST_DATE_PRESET_KEYS = ['today', 'this_month', 'last_month'];

export const DATE_RANGE_PRESETS = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'last_7_days', label: 'Last 7 Days' },
    { key: 'last_30_days', label: 'Last 30 Days' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'custom', label: 'Custom Range' },
];

function pad(value) {
    return String(value).padStart(2, '0');
}

export function toIsoDate(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function todayDateRange(referenceDate = new Date()) {
    const iso = toIsoDate(referenceDate);

    return { from_date: iso, to_date: iso };
}

export function yesterdayDateRange(referenceDate = new Date()) {
    const yesterday = new Date(referenceDate);
    yesterday.setDate(yesterday.getDate() - 1);
    const iso = toIsoDate(yesterday);

    return { from_date: iso, to_date: iso };
}

export function lastDaysDateRange(days, referenceDate = new Date()) {
    const end = new Date(referenceDate);
    const start = new Date(referenceDate);
    start.setDate(start.getDate() - (days - 1));

    return {
        from_date: toIsoDate(start),
        to_date: toIsoDate(end),
    };
}

export function lastSevenDaysDateRange(referenceDate = new Date()) {
    return lastDaysDateRange(7, referenceDate);
}

export function lastThirtyDaysDateRange(referenceDate = new Date()) {
    return lastDaysDateRange(30, referenceDate);
}

export function thisMonthDateRange(referenceDate = new Date()) {
    const year = referenceDate.getFullYear();
    const month = referenceDate.getMonth();
    const lastDay = new Date(year, month + 1, 0).getDate();

    return {
        from_date: `${year}-${pad(month + 1)}-01`,
        to_date: `${year}-${pad(month + 1)}-${pad(lastDay)}`,
    };
}

export function lastMonthDateRange(referenceDate = new Date()) {
    const year = referenceDate.getFullYear();
    const month = referenceDate.getMonth() - 1;
    const anchor = new Date(year, month, 1);
    const lastDay = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0).getDate();

    return {
        from_date: `${anchor.getFullYear()}-${pad(anchor.getMonth() + 1)}-01`,
        to_date: `${anchor.getFullYear()}-${pad(anchor.getMonth() + 1)}-${pad(lastDay)}`,
    };
}

export function resolveDateRangePreset(presetKey, referenceDate = new Date()) {
    switch (presetKey) {
        case 'yesterday':
            return yesterdayDateRange(referenceDate);
        case 'last_7_days':
            return lastSevenDaysDateRange(referenceDate);
        case 'last_30_days':
            return lastThirtyDaysDateRange(referenceDate);
        case 'this_month':
            return thisMonthDateRange(referenceDate);
        case 'last_month':
            return lastMonthDateRange(referenceDate);
        case 'custom':
            return todayDateRange(referenceDate);
        case 'today':
        default:
            return todayDateRange(referenceDate);
    }
}

export function detectDateRangePreset(fromDate, toDate, referenceDate = new Date()) {
    for (const preset of DATE_RANGE_PRESETS) {
        if (preset.key === 'custom') {
            continue;
        }

        const range = resolveDateRangePreset(preset.key, referenceDate);

        if (range.from_date === fromDate && range.to_date === toDate) {
            return preset.key;
        }
    }

    return 'custom';
}

export function defaultDashboardTripRange(referenceDate = new Date()) {
    return thisMonthDateRange(referenceDate);
}

export function formatDateRangeLabel(fromDate, toDate) {
    const format = (iso) =>
        new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });

    if (!fromDate || !toDate) {
        return 'Select date range';
    }

    if (fromDate === toDate) {
        return format(fromDate);
    }

    return `${format(fromDate)} - ${format(toDate)}`;
}
